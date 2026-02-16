<?php

namespace Drupal\season_filter\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by all seasons based on field_date_from.
 *
 * @ViewsFilter("season_filter")
 */
class SeasonFilter extends FilterPluginBase {

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
    $options['exposed'] = ['default' => FALSE];
    $options['expose']['contains']['identifier'] = ['default' => 'season'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['help'] = [
      '#type' => 'item',
      '#title' => $this->t('Information'),
      '#markup' => $this->t('This filter uses field_date_from for season filtering. When exposed, visitors can select multiple seasons.'),
      '#weight' => -10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    return [
      'in' => [
        'title' => $this->t('Is one of'),
        'short' => $this->t('in'),
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Seasons'),
      '#options' => $this->getValueOptions(),
      '#default_value' => $this->value,
      '#required' => $this->options['exposed'] ? FALSE : TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'winter' => $this->t('Winter'),
        'spring' => $this->t('Spring'),
        'summer' => $this->t('Summer'),
        'autumn' => $this->t('Autumn'),
      ];
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // Получаем значение из exposed формы
    $identifier = $this->options['expose']['identifier'];

    if (isset($input[$identifier])) {
      $value = $input[$identifier];

      // Фильтруем только выбранные значения
      if (is_array($value)) {
        $this->value = array_filter($value);
      } else {
        $this->value = [];
      }
    } else {
      $this->value = [];
    }

    return parent::acceptExposedInput($input);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // Определяем таблицу для field_date_from
    $entity_type_id = $this->getEntityType();
    if (!$entity_type_id) {
      return;
    }

    // Получаем выбранные сезоны
    $selected_seasons = $this->getSelectedSeasons();

    // Если нет выбранных сезонов и фильтр раскрытый - ничего не фильтруем
    if (empty($selected_seasons) && $this->options['exposed']) {
      return;
    }

    // Если фильтр не раскрытый и нет значений - используем стандартное поведение
    if (empty($selected_seasons) && !$this->options['exposed']) {
      $selected_seasons = ['winter', 'spring', 'summer', 'autumn'];
    }

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
   * Получает выбранные сезоны из значения.
   */
  protected function getSelectedSeasons() {
    $selected = [];

    if (is_array($this->value)) {
      foreach ($this->value as $season => $value) {
        if (!empty($value) && $value !== 0 && $value !== '0') {
          $selected[] = $season;
        }
      }
    }

    return $selected;
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
      return $this->t('Exposed: select seasons');
    }
    return $this->t('Filters by all seasons based on field_date_from');
  }

}
