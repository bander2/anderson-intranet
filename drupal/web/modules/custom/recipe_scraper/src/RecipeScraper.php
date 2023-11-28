<?php

namespace Drupal\recipe_scraper;

use Drupal\recipe_scraper\Exception\ParseException;
use Exception;
use GuzzleHttp\ClientInterface;

class RecipeScraper implements ScraperInterface {

  /**
   * @var ClientInterface
   */
  protected $client;

  public function __construct(ClientInterface $client) {
    $this->client = $client;
  }

  /**
   * @param string $url
   * @return array
   */
  public function scrape(string $url): array {
    $payload = $this->client->request('GET', 'http://scraper/recipe/?'.http_build_query(['url' => $url]));

    if ($payload->getStatusCode() == 422) {
      // Could not parse.
      throw new ParseException();
    }

    if ($payload->getStatusCode() != 200) {
      throw new Exception();
    }

    $data = json_decode($payload->getBody()->getContents(), TRUE);

    //dpm($data);
    return $data;
  }
}
