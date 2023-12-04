<?php

namespace Drupal\recipe_scraper;

use Drupal\Core\Entity\EntityInterface;
use Spatie\SchemaOrg\Contracts\ThingContract;

interface TransformerInterface {

  /**
   * Perform the transformation.
   *
   * @param array $data
   * @return EntityInterface
   */
  public function transform(ThingContract $data): EntityInterface;
}
