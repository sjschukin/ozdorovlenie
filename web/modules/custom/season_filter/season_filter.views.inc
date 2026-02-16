<?php

/**
 * @file
 * Views integration for Season Filter module.
 */

/**
 * Implements hook_views_data().
 */
function season_filter_views_data() {
  $data = [];

  // Add our custom filter to node table
  $data['node']['season_filter'] = [
    'title' => t('Season filter (all seasons)'),
    'help' => t('Filter content by all seasons based on field_date_from'),
    'filter' => [
      'id' => 'season_filter',
      'field' => 'nid',
    ],
  ];

  return $data;
}
