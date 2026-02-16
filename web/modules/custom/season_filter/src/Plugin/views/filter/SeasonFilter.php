<?php

namespace Drupal\season_filter\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
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
  public function query() {
    $this->ensureMyTable();

    // Определяем таблицу для field_date_from
    $entity_type_id = $this->getEntityType();
    if (!$entity_type_id) {
      return;
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

    // Условия для ВСЕХ сезонов (любая дата)
    // Просто проверяем что поле не NULL
    $this->query->addWhereExpression($this->options['group'], "$table_alias.field_date_from_value IS NOT NULL");
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
    return $this->t('Filters by all seasons based on field_date_from');
  }

}
