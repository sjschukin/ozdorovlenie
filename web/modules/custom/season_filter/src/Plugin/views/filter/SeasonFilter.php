<?php

namespace Drupal\season_filter\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by all seasons based on field_date_from.
 *
 * @ViewsFilter("season_filter")
 */
class SeasonFilter extends InOperator {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['exposed'] = ['default' => TRUE]; // По умолчанию делаем раскрытым
    $options['expose']['contains']['identifier'] = ['default' => 'season'];
    $options['expose']['contains']['label'] = ['default' => t('Сезон')];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Скрываем ненужные опции
    $form['expose']['label']['#default_value'] = t('Season');

    $form['help'] = [
      '#type' => 'item',
      '#title' => $this->t('Information'),
      '#markup' => $this->t('This filter uses field_date_from for season filtering.'),
      '#weight' => -10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    // Определяем варианты сезонов для выпадающего списка
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'winter' => $this->t('Зима'),
        'spring' => $this->t('Весна'),
        'summer' => $this->t('Лето'),
        'autumn' => $this->t('Осень'),
      ];
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    return [
      'in' => [
        'title' => $this->t('Is one of'),
        'short' => $this->t('in'),
        'short_single' => $this->t('='),
        'method' => 'opSimple',
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    if (empty($this->value) || !is_array($this->value)) {
      return;
    }

    $valid_seasons = array_keys($this->getValueOptions());
    $selected_seasons = array_filter($this->value, function ($season) use ($valid_seasons) {
      return in_array($season, $valid_seasons);
    });

    if (empty($selected_seasons)) {
      return;
    }

    // Определяем таблицу для field_date_from
    $entity_type_id = $this->getEntityType();

    // Получаем выбранные сезоны из экспоузд формы
    $selected_seasons = $this->value;

    // Таблица для field_date_from
    $field_table = $entity_type_id . '__field_date_from';
    $schema = $this->database->schema();

    if (!$schema->tableExists($field_table)) {
      return;
    }

    // Присоединяем таблицу
    $table_alias = $this->query->ensureTable($field_table, $this->relationship);

    $database_type = $this->database->driver();

    // Выражение для извлечения месяца
    switch ($database_type) {
      case 'mysql':
        $month_expression = "MONTH($table_alias.field_date_from_value)";
        break;
      case 'pgsql':
        $month_expression = "EXTRACT(MONTH FROM $table_alias.field_date_from_value)";
        break;
      default:
        $month_expression = "EXTRACT(MONTH FROM $table_alias.field_date_from_value)";
    }

    // Строим условия для выбранных сезонов
    $conditions = [];
    foreach ($selected_seasons as $season) {
      $condition = $this->getSeasonCondition($season, $month_expression);
      if ($condition) {
        $conditions[] = $condition;
      }
    }

    if (empty($conditions)) {
      return;
    }

    $combined_condition = implode(' OR ', $conditions);

    // Добавляем условие WHERE
    $this->query->addWhereExpression($this->options['group'], "($combined_condition)");
  }

  /**
   * Получает условие для конкретного сезона.
   */
  protected function getSeasonCondition($season, $month_expression) {
    $ranges = [
      'winter' => [12, 1, 2],
      'spring' => [3, 4, 5],
      'summer' => [6, 7, 8],
      'autumn' => [9, 10, 11],
    ];

    if (!isset($ranges[$season])) {
      return '';
    }

    $months = $ranges[$season];
    $month_conditions = [];
    foreach ($months as $month) {
      $month_conditions[] = "$month_expression = $month";
    }

    return '(' . implode(' OR ', $month_conditions) . ')';
  }

  /**
   * Get entity type from the view.
   */
  public function getEntityType() {
    if ($this->view && $this->view->getBaseEntityType()) {
      return $this->view->getBaseEntityType()->id();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isExposed()) {
      return $this->t('Exposed: allows selection of seasons');
    }
    return $this->t('Filters by selected seasons based on field_date_from');
  }
}
