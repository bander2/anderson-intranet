<?php

namespace Drupal\expenses;

use Drupal\taxonomy\TermInterface;

interface GuesserInterface {

  /**
   * @param string $text
   * @return \Drupal\taxonomy\TermInterface|null
   */
  public function guess(string $text): ?TermInterface;
}
