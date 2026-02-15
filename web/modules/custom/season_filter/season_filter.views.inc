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

  // Add our custom filter to all tables that might have date fields
  // This is a generic approach - you might want to limit to specific tables
  $data['node']['season_filter'] = [
    'title' => t('Season filter'),
    'help' => t('Filter content by season based on a date field.'),
    'filter' => [
      'id' => 'season_filter',
      'field' => 'nid', // This is a dummy field, we'll use the configured field
    ],
  ];

  // Add for other entity types if needed
  $data['users']['season_filter'] = [
    'title' => t('Season filter'),
    'help' => t('Filter users by season based on a date field.'),
    'filter' => [
      'id' => 'season_filter',
      'field' => 'uid',
    ],
  ];

  $data['taxonomy_term_data']['season_filter'] = [
    'title' => t('Season filter'),
    'help' => t('Filter terms by season based on a date field.'),
    'filter' => [
      'id' => 'season_filter',
      'field' => 'tid',
    ],
  ];

  return $data;
}
