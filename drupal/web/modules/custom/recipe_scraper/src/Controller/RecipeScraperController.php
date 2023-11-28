<?php declare(strict_types = 1);

namespace Drupal\recipe_scraper\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ingredient\Utility\IngredientUnitUtility;

/**
 * Returns responses for Recipe Scraper routes.
 */
final class RecipeScraperController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    /** @var IngredientUnitUtility $utility */
    $utility = \Drupal::getContainer()->get('ingredient.unit');
    $units = $utility->getConfiguredUnits();

    foreach ($units as $unit => $aliases) {
      foreach ($aliases as $alias) {
        $alias = is_array($alias) ? $alias : [$alias];
        foreach ($alias as $candidate) {

        }
      }
    }

    dpm($utility->getConfiguredUnits());

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
