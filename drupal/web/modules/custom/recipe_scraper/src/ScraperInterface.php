<?php

namespace Drupal\recipe_scraper;

interface ScraperInterface {

  /**
   * Scrape a URL.
   *
   * @param string $url
   * @return array
   */
  public function scrape(string $url): array;
}
