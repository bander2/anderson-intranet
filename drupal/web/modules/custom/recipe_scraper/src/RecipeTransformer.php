<?php

namespace Drupal\recipe_scraper;

use DateInterval;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\Client;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\HowToStep;
use Spatie\SchemaOrg\NutritionInformation;
use Spatie\SchemaOrg\Recipe;

class RecipeTransformer implements TransformerInterface {

  private IngredientNormalizer $ingredientNormalizer;
  private EntityTypeManagerInterface $entityTypeManager;
  private FileRepositoryInterface $fileRepository;
  private AccountInterface $account;
  private Client $client;

  public function __construct(
    IngredientNormalizer $ingredientNormalizer,
    EntityTypeManagerInterface $entityTypeManager,
    FileRepositoryInterface $fileRepository,
    AccountInterface $account,
    Client $client
  ) {
    $this->ingredientNormalizer = $ingredientNormalizer;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileRepository = $fileRepository;
    $this->account = $account;
    $this->client = $client;
  }

  /**
   * @param array $data
   * @return EntityInterface
   */
  public function transform(ThingContract $data): EntityInterface {
    if (!$data instanceof Recipe) {
      throw new \InvalidArgumentException();
    }

    [$yield_amount, $yield_unit] = $this->splitYield($data['recipeYield']);

    $node = Node::create([
      'type' => 'recipe',
      'title' => $data->getProperty('name'),
      'recipe_description' => [
        'value' => $data->getProperty('description'),
        'format' => 'basic_html',
      ],
      'recipe_ingredient' => array_map([$this, 'createIngredient'], $data->getProperty('recipeIngredient')),
      'recipe_instructions' => [
        'value' => Markup::create('<ol>' . array_reduce($data->getProperty('recipeInstructions'), function ($cary, HowToStep $item) {
          $cary .= "<li>{$item->getProperty('text')}</li>";
          return $cary;
        }, '') . '</ol>'),
        'format' => 'basic_html',
      ],
      'recipe_cook_time' => (new DateInterval($data->getProperty('cookTime')))->format('%i minutes'),
      'recipe_prep_time' => (new DateInterval($data->getProperty('prepTime')))->format('%i minutes'),
      'recipe_yield_amount' => $yield_amount,
      'recipe_yield_units' => $yield_unit,
    ]);

    if ($data->getProperty('image')) {
      $img_uri = $data->getProperty('image')[0];
      $response = $this->client->get($img_uri);
      $image_data = $response->getBody()->getContents();

      //$image_data = file_get_contents($img_uri);
      $image = $this->fileRepository->writeData($image_data, 'public://' . basename($img_uri), FileSystemInterface::EXISTS_RENAME);
      $image_media = $this->entityTypeManager
        ->getStorage('media')
        ->create([
          'name' => $data['title'],
          'bundle' => 'image',
          'uid' => $this->account->id(),
          'field_media_image' => [
            'target_id' => $image->id(),
            'alt' => $data->getProperty('name'),
            'title' => $data->getProperty('name'),
          ],
        ]);
      $image_media->save();
      $node->field_picture = $image_media;
    }

    /** @var NutritionInformation $nutrition */
    $nutrition = $data->getProperty('nutrition');
    if ($nutrition instanceof NutritionInformation) {
      foreach ($nutrition->toArray() as $name => $value) {
        if (str_starts_with($name, '@')) {
          continue;
        }

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
          ->loadByProperties(['vid' => 'unit', 'name' => $unitName]);

        if (!empty($units)) {
          $unit = reset($units);
        } else {
          $unit = Term::create(['vid' => 'unit', 'name' => $unitName]);
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
  private function splitYield(array|string $yield): array {
    if (is_array($yield) && count($yield) == 1) {
      return [$yield[0], 'servings'];
    }

    if (is_array($yield)) {
      $yield = array_shift($yield);
    }

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
