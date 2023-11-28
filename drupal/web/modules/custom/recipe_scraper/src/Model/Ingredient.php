<?php

namespace Drupal\recipe_scraper\Model;

class Ingredient {
  private string $name;
  private string $quantity;
  private string $unit;
  private string $notes;

  public function __construct(string $name, string $quantity, string $unit, string $notes = '') {
    $this->name = $name;
    $this->quantity = $quantity;
    $this->unit = $unit;
    $this->notes = $notes;
  }

  public function getName(): string {
    return $this->name;
  }

  public function setName(string $name): void {
    $this->name = $name;
  }

  public function getQuantity(): string {
    return $this->quantity;
  }

  public function setQuantity(string $quantity): void {
    $this->quantity = $quantity;
  }

  public function getUnit(): string {
    return $this->unit;
  }

  public function setUnit(string $unit): void {
    $this->unit = $unit;
  }

  public function getNotes(): string {
    return $this->notes;
  }

  public function setNotes(string $notes): void {
    $this->notes = $notes;
  }
}
