<?php

namespace Drupal\recipe_scraper;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

class RecipeTransformer implements TransformerInterface {

  private IngredientNormalizer $ingredientNormalizer;
  private EntityTypeManagerInterface $entityTypeManager;
  private FileRepositoryInterface $fileRepository;
  private AccountInterface $account;

  public function __construct(IngredientNormalizer $ingredientNormalizer, EntityTypeManagerInterface $entityTypeManager, FileRepositoryInterface $fileRepository, AccountInterface $account) {
    $this->ingredientNormalizer = $ingredientNormalizer;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileRepository = $fileRepository;
    $this->account = $account;
  }

  /**
   * @param array $data
   * @return EntityInterface
   */
  public function transform(array $data): EntityInterface {
    [$yield_amount, $yield_unit] = $this->splitYield($data['yields']);

    $node = Node::create([
      'type' => 'recipe',
      'title' => $data['title'],
      'recipe_description' => [
        'value' => $data['description'],
        'format' => 'basic_html',
      ],
      'recipe_ingredient' => array_map([$this, 'createIngredient'], $data['ingredients']),
      'recipe_instructions' => [
        'value' => Markup::create('<ol>' . array_reduce($data['instructions_list'], function ($cary, $item) {
          $cary .= "<li>{$item}</li>";
          return $cary;
        }, '') . '</ol>'),
        'format' => 'basic_html',
      ],
      'recipe_cook_time' => $data['cook_time'],
      'recipe_prep_time' => $data['prep_time'],
      'recipe_yield_amount' => $yield_amount,
      'recipe_yield_units' => $yield_unit,
    ]);

    if ($data['image']) {
      $image_data = file_get_contents($data['image']);
      $image = $this->fileRepository->writeData($image_data, 'public://' . basename($data['image']), FileSystemInterface::EXISTS_RENAME);
      $image_media = $this->entityTypeManager
        ->getStorage('media')
        ->create([
          'name' => $data['title'],
          'bundle' => 'image',
          'uid' => $this->account->id(),
          'field_media_image' => [
            'target_id' => $image->id(),
            'alt' => $data['title'],
            'title' => $data['title'],
          ],
        ]);
      $image_media->save();
      $node->field_picture = $image_media;
    }

    if (!empty($data['nutrients'])) {
      foreach ($data['nutrients'] as $name => $value) {
        $nutrients = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->loadByProperties(['vid' => 'nutrients', 'name' => $name]);

        if (!empty($nutrients)) {
          $nutrient = reset($nutrients);
        } else {
          $nutrient = Term::create(['vid' => 'nutrients', 'name' => $name]);
          $nutrient->save();
        }

        $values = explode(' ', $value);
        $amount = array_shift($values);
        $unitName = implode(' ', $values);
        $units = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->loadByProperties(['vid' => 'units', 'name' => $unitName]);

        if (!empty($units)) {
          $unit = reset($units);
        } else {
          $unit = Term::create(['vid' => 'units', 'name' => $unitName]);
          $unit->save();
        }

        $paragraph = Paragraph::create([
          'type' => 'nutrient',
          'field_nutrient' => $nutrient,
          'field_amount' => $amount,
          'field_unit' => $unit,
        ]);
        $paragraph->save();

        $node->field_nutrients[] = $paragraph;
      }
    }

    $node->save();

    return $node;
  }

  /**
   * @param string $yield
   * @return array
   */
  private function splitYield(string $yield): array {
    $chars = str_split($yield);
    $index = 0;
    foreach ($chars as $char) {
      if (!is_numeric($char)) {
        break;
      }
      $index++;
    }

    return [
      trim(substr($yield, 0, $index)),
      trim(substr($yield, $index)),
    ];
  }

  /**
   * @param string $value
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createIngredient(string $value): array {
    $ingredientModel = $this->ingredientNormalizer->normalize($value);

    $ingredient = $this->entityTypeManager
      ->getStorage('ingredient')
      ->loadByProperties(['name' => $ingredientModel->getName()]);

    $ingredient = empty($ingredient) ? $this->entityTypeManager
        ->getStorage('ingredient')
        ->create([
          'name' =>$ingredientModel->getName(),
        ]) : reset($ingredient);

    if ($ingredient->isNew()) {
      $ingredient->save();
    }

    return [
      'target_id' => $ingredient->id(),
      'quantity' => $ingredientModel->getQuantity(),
      'unit_key' => $ingredientModel->getUnit(),
      'note' => $ingredientModel->getNotes(),
    ];
  }
}
