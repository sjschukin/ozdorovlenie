<?php

namespace Drupal\season_filter\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\season_filter\Services\SeasonService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Database\Connection;

/**
 * Filter by season based on date field.
 *
 * @ViewsFilter("season_filter")
 */
class SeasonFilter extends FilterPluginBase {

  /**
   * The season service.
   *
   * @var \Drupal\season_filter\Services\SeasonService
   */
  protected $seasonService;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SeasonService $season_service, EntityFieldManagerInterface $entity_field_manager, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->seasonService = $season_service;
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('season_filter.season_service'),
      $container->get('entity_field.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['hemisphere'] = ['default' => 'northern'];
    $options['date_field'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // Сначала вызываем родительский метод
    parent::buildOptionsForm($form, $form_state);

    // Добавляем отладочное сообщение, чтобы убедиться, что метод вызывается
    \Drupal::messenger()->addStatus('Season Filter options form is loaded');

    // Get all date fields from the entity type.
    $date_fields = $this->getDateFields();

    $form['date_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Date field'),
      '#description' => $this->t('Select the date field to use for season filtering.'),
      '#options' => $date_fields,
      '#default_value' => $this->options['date_field'],
      '#required' => TRUE,
      '#weight' => -10, // Добавляем вес, чтобы поднять выше
    ];

    $form['hemisphere'] = [
      '#type' => 'select',
      '#title' => $this->t('Hemisphere'),
      '#description' => $this->t('Select the hemisphere for season calculation.'),
      '#options' => [
        'northern' => $this->t('Northern Hemisphere'),
        'southern' => $this->t('Southern Hemisphere'),
      ],
      '#default_value' => $this->options['hemisphere'],
      '#weight' => -9,
    ];

    // Add information about available operators.
    $form['operator_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Available operators'),
      '#description' => $this->t('This filter supports "Is one of" and "Is not one of" operators for selecting multiple seasons.'),
      '#weight' => -8,
    ];

    // Добавляем информацию о том, где находятся настройки
    $form['settings_info'] = [
      '#type' => 'item',
      '#markup' => $this->t('These settings will appear above the operator selection.'),
      '#weight' => -11,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = [
      'in' => [
        'title' => $this->t('Is one of'),
        'short' => $this->t('in'),
        'values' => 1,
        'method' => 'opIn',
      ],
      'not in' => [
        'title' => $this->t('Is not one of'),
        'short' => $this->t('not in'),
        'values' => 1,
        'method' => 'opNotIn',
      ],
    ];
    return $operators;
  }

/**
 * {@inheritdoc}
 */
public function query() {
  $this->ensureMyTable();

  if (empty($this->options['date_field'])) {
    return;
  }

  // Get the selected date field
  $date_field = $this->options['date_field'];

  // Get the table alias for this filter
  $table_alias = $this->tableAlias ?: $this->table;

  // For field API fields, we need to ensure the correct table is used
  $real_table = $this->getFieldTable($date_field);
  if ($real_table) {
    $table_alias = $real_table;
  }

  // Get selected seasons - с проверкой типа
  $selected_seasons = [];
  if (is_array($this->value)) {
    $selected_seasons = array_filter($this->value);
  } elseif (!empty($this->value)) {
    $selected_seasons = [$this->value];
  }

  if (empty($selected_seasons)) {
    return;
  }

  $hemisphere = $this->options['hemisphere'];
  $not_in = $this->operator === 'not in';
  $database_type = $this->database->driver();

  // Build month expression based on database type
  $month_expression = $this->getMonthExpression($table_alias, $date_field, $database_type);

  // Build conditions for each selected season
  $conditions = [];
  foreach ($selected_seasons as $season) {
    $season_condition = $this->getSeasonCondition($season, $month_expression, $hemisphere);
    if ($season_condition) {
      $conditions[] = $season_condition;
    }
  }

  if (empty($conditions)) {
    return;
  }

  // Combine conditions with OR
  $combined_condition = implode(' OR ', $conditions);

  if ($not_in) {
    $this->query->addWhereExpression($this->options['group'], "NOT ($combined_condition)");
  } else {
    $this->query->addWhereExpression($this->options['group'], "($combined_condition)");
  }
}

  /**
   * Get the correct table for a field.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string|false
   *   The table name or FALSE if not found.
   */
  protected function getFieldTable($field_name) {
    // For base fields like created/changed, use the base table
    if (in_array($field_name, ['created', 'changed', 'timestamp'])) {
      return $this->table;
    }

    // For entity fields, try to find the data table
    $entity_type_id = $this->getEntityType();
    if ($entity_type_id) {
      $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);

      // Check if field exists in base table
      $base_table = $entity_type->getBaseTable();
      $data_table = $entity_type->getDataTable();
      $revision_table = $entity_type->getRevisionTable();

      // Try to find which table contains this field
      $schema = $this->database->schema();

      $possible_tables = [
        $base_table,
        $data_table,
        $revision_table,
        $base_table . '__' . $field_name,
        $data_table . '__' . $field_name,
        'node__' . $field_name,
        'taxonomy_term__' . $field_name,
        'user__' . $field_name,
      ];

      foreach (array_filter($possible_tables) as $table) {
        if ($schema->tableExists($table) && $schema->fieldExists($table, $field_name)) {
          return $table;
        }
      }
    }

    return FALSE;
  }

  /**
   * Get month expression based on database type.
   *
   * @param string $table
   *   The table name.
   * @param string $field
   *   The field name.
   * @param string $database_type
   *   The database type.
   *
   * @return string
   *   The SQL expression for extracting month.
   */
  protected function getMonthExpression($table, $field, $database_type) {
    switch ($database_type) {
      case 'mysql':
        return "MONTH($table.$field)";

      case 'pgsql':
        return "EXTRACT(MONTH FROM $table.$field)";

      case 'sqlite':
        return "CAST(strftime('%m', $table.$field) AS INTEGER)";

      default:
        return "EXTRACT(MONTH FROM $table.$field)";
    }
  }

  /**
   * Get SQL condition for a specific season.
   *
   * @param string $season
   *   The season name.
   * @param string $month_expression
   *   The SQL expression for month.
   * @param string $hemisphere
   *   The hemisphere.
   *
   * @return string
   *   The SQL condition.
   */
  protected function getSeasonCondition($season, $month_expression, $hemisphere) {
    // Define month ranges for seasons
    $ranges = $this->getSeasonMonthRanges($hemisphere);

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
   * Get month ranges for seasons based on hemisphere.
   *
   * @param string $hemisphere
   *   The hemisphere.
   *
   * @return array
   *   Array of season month ranges.
   */
  protected function getSeasonMonthRanges($hemisphere) {
    if ($hemisphere === 'northern') {
      return [
        'winter' => [12, 1, 2],
        'spring' => [3, 4, 5],
        'summer' => [6, 7, 8],
        'autumn' => [9, 10, 11],
      ];
    } else {
      return [
        'summer' => [12, 1, 2],
        'autumn' => [3, 4, 5],
        'winter' => [6, 7, 8],
        'spring' => [9, 10, 11],
      ];
    }
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
protected function valueForm(&$form, FormStateInterface $form_state) {
  // Убеждаемся, что value имеет правильную структуру
  $default_value = $this->value;

  // Преобразуем значение в формат, подходящий для checkboxes
  if (is_array($default_value)) {
    // Если это массив с ключами-сезонами и булевыми значениями
    $options = $this->getValueOptions();
    $defaults = [];
    foreach ($options as $key => $label) {
      $defaults[$key] = !empty($default_value[$key]) ? $key : 0;
    }
    $default_value = $defaults;
  }

  $form['value'] = [
    '#type' => 'checkboxes',
    '#title' => $this->t('Seasons'),
    '#options' => $this->getValueOptions(),
    '#default_value' => $default_value,
    '#required' => TRUE,
  ];
}

/**
 * {@inheritdoc}
 */
public function validate() {
  // Проверяем, что value является массивом перед фильтрацией
  if (is_array($this->value)) {
    $this->value = array_filter($this->value);
  } else {
    // Если это не массив (например, строка), преобразуем в массив или очищаем
    $this->value = !empty($this->value) ? [$this->value] : [];
  }
  return parent::validate();
}

/**
 * {@inheritdoc}
 */
public function submitOptionsForm(&$form, FormStateInterface $form_state) {
  parent::submitOptionsForm($form, $form_state);

  // Обрабатываем значения из формы настроек
  $options = $form_state->getValue('options');
  if (isset($options['value'])) {
    // Убеждаемся, что value - это массив
    if (is_array($options['value'])) {
      // Убираем пустые значения
      $this->options['value'] = array_filter($options['value']);
    } else {
      // Если это не массив, преобразуем или очищаем
      $this->options['value'] = !empty($options['value']) ? [$options['value']] : [];
    }
  }
}

/**
 * {@inheritdoc}
 */
public function acceptExposedInput($input) {
  if (empty($this->options['exposed'])) {
    return TRUE;
  }

  // Обрабатываем значения из exposed filter
  if (isset($input[$this->options['expose']['identifier']])) {
    $value = $input[$this->options['expose']['identifier']];

    // Преобразуем значение в правильный формат
    if (is_array($value)) {
      // Убираем пустые значения (0)
      $this->value = array_filter($value);
    } else {
      // Если это не массив (например, строка), преобразуем в массив
      $this->value = !empty($value) ? [$value] : [];
    }
  }

  return parent::acceptExposedInput($input);
}

  /**
   * Get all date fields from the base table.
   *
   * @return array
   *   Array of date fields.
   */
  protected function getDateFields() {
    $options = [];

    // Get the entity type for this view
    $entity_type_id = $this->getEntityType();

    if ($entity_type_id) {
      $fields = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);

      foreach ($fields as $field_name => $field) {
        $type = $field->getType();
        // Check for date-related field types
        if (in_array($type, [
          'datetime',
          'date',
          'daterange',
          'timestamp',
          'created',
          'changed',
          'published_at',
          'expires',
        ])) {
          $options[$field_name] = $field->getLabel() . ' (' . $field_name . ' - ' . $type . ')';
        }
      }
    }

    // If no fields found, add some common ones as suggestions
    if (empty($options)) {
      $options['created'] = $this->t('Created date (base field)');
      $options['changed'] = $this->t('Changed date (base field)');
      $options['field_date_from'] = $this->t('Date From field (suggested)');
    }

    return $options;
  }

  /**
   * Get entity type from the view.
   *
   * @return string|null
   *   The entity type ID or NULL.
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
    $output = parent::adminSummary();
    if (!empty($this->options['date_field'])) {
      $output .= ', ' . $this->t('Field: @field', ['@field' => $this->options['date_field']]);
    }
    if (!empty($this->options['hemisphere'])) {
      $output .= ', ' . $this->t('Hemisphere: @hemisphere', ['@hemisphere' => $this->options['hemisphere']]);
    }
    return $output;
  }

}
