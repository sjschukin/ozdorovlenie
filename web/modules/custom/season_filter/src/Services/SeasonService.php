<?php

namespace Drupal\season_filter\Services;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Service for handling season-related operations.
 */
class SeasonService {

  /**
   * Get season for a specific date.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date object.
   * @param string $hemisphere
   *   The hemisphere ('northern' or 'southern').
   *
   * @return string
   *   The season name.
   */
  public function getSeasonForDate(DrupalDateTime $date, $hemisphere = 'northern') {
    $month = (int) $date->format('n');
    $day = (int) $date->format('j');

    if ($hemisphere === 'northern') {
      return $this->getNorthernHemisphereSeason($month, $day);
    }
    else {
      return $this->getSouthernHemisphereSeason($month, $day);
    }
  }

  /**
   * Get season for Northern hemisphere.
   */
  protected function getNorthernHemisphereSeason($month, $day) {
    // Meteorological seasons
    if ($month >= 3 && $month <= 5) {
      return 'spring';
    }
    elseif ($month >= 6 && $month <= 8) {
      return 'summer';
    }
    elseif ($month >= 9 && $month <= 11) {
      return 'autumn';
    }
    else {
      return 'winter';
    }
  }

  /**
   * Get season for Southern hemisphere.
   */
  protected function getSouthernHemisphereSeason($month, $day) {
    // Meteorological seasons for Southern hemisphere
    if ($month >= 9 && $month <= 11) {
      return 'spring';
    }
    elseif ($month >= 12 || $month <= 2) {
      return 'summer';
    }
    elseif ($month >= 3 && $month <= 5) {
      return 'autumn';
    }
    else {
      return 'winter';
    }
  }

  /**
   * Get all available seasons.
   *
   * @return array
   *   Array of seasons with machine names and labels.
   */
  public function getSeasons() {
    return [
      'winter' => t('Winter'),
      'spring' => t('Spring'),
      'summer' => t('Summer'),
      'autumn' => t('Autumn'),
    ];
  }

  /**
   * Check if a date falls within a specific season.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date to check.
   * @param string $season
   *   The season to check against.
   * @param string $hemisphere
   *   The hemisphere.
   *
   * @return bool
   *   TRUE if date is in the specified season.
   */
  public function isDateInSeason(DrupalDateTime $date, $season, $hemisphere = 'northern') {
    return $this->getSeasonForDate($date, $hemisphere) === $season;
  }

}
