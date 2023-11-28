<?php declare(strict_types = 1);

namespace Drupal\expenses\Controller;

use Crwlr\SchemaOrg\SchemaOrg;
use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Expenses routes.
 */
final class ExpensesController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $html = file_get_contents('https://www.hellofresh.com/recipes/classic-beef-chili-59b1b47b0534680a425efd72');

    $schemaOrgObjects = SchemaOrg::fromHtml($html);
    dpm($schemaOrgObjects);

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
