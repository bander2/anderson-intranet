<?php

namespace Drupal\recipe_scraper;

use Crwlr\SchemaOrg\SchemaOrg;
use Drupal\recipe_scraper\Exception\ParseException;
use GuzzleHttp\Client;
use Spatie\SchemaOrg\Recipe;

class RecipeScraper implements ScraperInterface {

  public function __construct(private readonly Client $client) {}

  /**
   * @param string $url
   * @return array
   */
  public function scrape(string $url): Recipe {
    $response = $this->client->get($url);
    $html = $response->getBody()->getContents();
    $schemaObjects = SchemaOrg::fromHtml($html);
    foreach ($schemaObjects as $schemaObject) {
      if ($schemaObject instanceof Recipe) {
        return $schemaObject;
      }
    }
    throw new ParseException("No recipe found on $url");
  }
}
