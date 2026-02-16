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
    \Drupal::logger('season_filter')->debug('acceptExposedInput - identifier: @id, input: @input', [
      '@id' => $identifier,
      '@input' => print_r($input, TRUE)
    ]);

    $selected_seasons = [];
    if (isset($input[$identifier])) {
      $value = $input[$identifier];

      // Обрабатываем как массив выбранных сезонов
      if (is_array($value)) {
        foreach ($value as $season => $v) {
          // Если значение равно '1' или не пустое, считаем сезон выбранным
          if ($v === '1' || $v === 1 || $v === $season) {
            $selected_seasons[] = $season;
          }
        }
      } else {
        // Если пришло одно значение (выбран один сезон)
        if (!empty($value) && $value !== '0') {
          $selected_seasons[] = $value;
        }
      }
    }

    $this->value = $selected_seasons;
    \Drupal::logger('season_filter')->debug('acceptExposedInput - set value to: @value', [
      '@value' => print_r($this->value, TRUE)
    ]);

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
      \Drupal::logger('season_filter')->debug('No entity type');
      return;
    }

    // Получаем выбранные сезоны
    $selected_seasons = $this->getSelectedSeasons();

    // Отладка: логируем выбранные сезоны
    \Drupal::logger('season_filter')->debug('Selected seasons: @seasons', [
      '@seasons' => implode(', ', $selected_seasons)
    ]);

    // Если нет выбранных сезонов и фильтр раскрытый - ничего не фильтруем
    if (empty($selected_seasons) && $this->options['exposed']) {
      \Drupal::logger('season_filter')->debug('No seasons selected, skipping filter');
      return;
    }

    // Если фильтр не раскрытый и нет значений - используем стандартное поведение
    if (empty($selected_seasons) && !$this->options['exposed']) {
      $selected_seasons = ['winter', 'spring', 'summer', 'autumn'];
      \Drupal::logger('season_filter')->debug('Using all seasons: @seasons', [
        '@seasons' => implode(', ', $selected_seasons)
      ]);
    }

    // Таблица для field_date_from
    $field_table = $entity_type_id . '__field_date_from';
    $schema = $this->database->schema();

    if (!$schema->tableExists($field_table)) {
      \Drupal::logger('season_filter')->error('Table @table does not exist', [
        '@table' => $field_table
      ]);
      return;
    }

    // Присоединяем таблицу
    $table_alias = $this->query->ensureTable($field_table, $this->relationship);
    \Drupal::logger('season_filter')->debug('Table alias: @alias', ['@alias' => $table_alias]);

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

    \Drupal::logger('season_filter')->debug('Month expression: @expr', ['@expr' => $month_expression]);

    // Строим условия для выбранных сезонов
    $conditions = [];
    foreach ($selected_seasons as $season) {
      $condition = $this->getSeasonCondition($season, $month_expression);
      if ($condition) {
        $conditions[] = $condition;
        \Drupal::logger('season_filter')->debug('Condition for @season: @cond', [
          '@season' => $season,
          '@cond' => $condition
        ]);
      }
    }

    if (empty($conditions)) {
      \Drupal::logger('season_filter')->debug('No conditions built');
      return;
    }

    $combined_condition = implode(' OR ', $conditions);
    \Drupal::logger('season_filter')->debug('Combined condition: @cond', ['@cond' => $combined_condition]);

    // Добавляем условие WHERE
    $this->query->addWhereExpression($this->options['group'], "($combined_condition)");
    \Drupal::logger('season_filter')->debug('Added where condition to query');
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
    \Drupal::logger('season_filter')->debug('Value in getSelectedSeasons: @value', [
      '@value' => print_r($this->value, TRUE)
    ]);

    if (is_array($this->value)) {
      foreach ($this->value as $season) {
        if (in_array($season, ['winter', 'spring', 'summer', 'autumn'])) {
          $selected[] = $season;
        }
      }
    }

    \Drupal::logger('season_filter')->debug('Selected seasons after processing: @seasons', [
      '@seasons' => implode(', ', $selected)
    ]);
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
