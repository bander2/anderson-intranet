<?php

namespace Drupal\expenses;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

class CategoryGuesser implements GuesserInterface {
  private EntityTypeManagerInterface $entityTypeManager;
  private array $categories = [];

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getCategories(): array {
    if (empty($this->categories)) {
      $this->categories = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'expense_categories']);
    }
    return $this->categories;
  }

  /**
   * @param string $text
   * @return \Drupal\taxonomy\TermInterface|null
   */
  public function guess(string $text): ?TermInterface {
    foreach ($this->getCategories() as $category) {
      foreach ($category->field_synonyms->getValue() as $synonym) {
        if (str_contains(strtolower($text), strtolower($synonym['value']))) {
          return $category;
        }
      }
    }

    return NULL;
  }
}
