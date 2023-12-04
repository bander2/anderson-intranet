<?php

namespace Drupal\recipe_scraper;

use Spatie\SchemaOrg\Contracts\ThingContract;

interface ScraperInterface {

  /**
   * Scrape a URL.
   *
   * @param string $url
   * @return array
   */
  public function scrape(string $url): ThingContract;
}
