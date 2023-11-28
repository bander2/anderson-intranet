<?php

namespace Drupal\recipe_scraper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\ingredient\Utility\IngredientUnitUtility;
use Drupal\recipe_scraper\Model\Ingredient;

class IngredientNormalizer {

  /**
   * @var IngredientUnitUtility
   */
  private $ingredientUnitUtility;

  private $fractions = [
    '½' => '1/2',
    '⅓' => '1/3',
    '⅔' => '2/3',
    '¼' => '1/4',
    '¾' => '3/4',
    '⅕' => '1/5',
    '⅖' => '2/5',
    '⅗' => '3/5',
    '⅘' => '4/5',
    '⅙' => '1/6',
    '⅚' => '5/6',
    '⅐' => '1/7',
    '⅛' => '1/8',
    '⅜' => '3/8',
    '⅝' => '5/8',
    '⅞' => '7/8',
  ];

  public function __construct(IngredientUnitUtility $ingredientUnitUtility) {
    $this->ingredientUnitUtility = $ingredientUnitUtility;
  }

  /**
   * @param $ingredient
   * @return array
   */
  public function normalize($ingredient): Ingredient {
    $ingredient = strtolower($ingredient);
    $ingredient = str_replace(array_keys($this->fractions), array_values($this->fractions), $ingredient);
    $units = $this->ingredientUnitUtility->getConfiguredUnits();

    foreach ($units as $unit => $aliases) {
      foreach ($aliases as $alias) {
        if (empty($alias)) {
          continue;
        }

        $alias = is_array($alias) ? $alias : [$alias];
        foreach ($alias as $candidate) {
          if (str_contains($ingredient, " {$candidate} ")) {
            $pos = strpos($ingredient, " {$candidate} ");
            return new Ingredient(
              trim(substr($ingredient, $pos + strlen(" {$candidate} "))),
              trim(substr($ingredient, 0, $pos)),
              $unit,
            );
          }
        }
      }
    }

    // No unit found
    $index = 0;
    foreach (str_split($ingredient) as $char) {
      if (ctype_alpha($char)) {
        break;
      }
      $index++;
    }
    return new Ingredient(
      trim(substr($ingredient, $index)),
      trim(substr($ingredient, 0, $index)),
      'unknown'
    );
  }
}
