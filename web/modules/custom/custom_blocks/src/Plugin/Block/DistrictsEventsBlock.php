<?php

namespace Drupal\custom_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'Districts Events' block.
 *
 * @Block(
 *   id = "districts_events_block",
 *   admin_label = @Translation("Districts Events Block"),
 *   category = @Translation("Custom Blocks")
 * )
 */
class DistrictsEventsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $districts_data = custom_blocks_get_districts_events_data();

    return [
      '#theme' => 'districts_events_block',
      '#districts_data' => $districts_data,
      '#cache' => [
        'max-age' => 1800,
        'tags' => ['node_list', 'taxonomy_term_list'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      ['node_list', 'taxonomy_term_list']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      ['url.path', 'url.query_args']
    );
  }
}
