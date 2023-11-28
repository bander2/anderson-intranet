<?php

namespace Drupal\expenses;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\csv_serialization\Encoder\CsvEncoder;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class CsvExpenseImporter {

  private CsvEncoder $csvEncoder;
  private EntityTypeManagerInterface $entityTypeManager;
  private AccountInterface $currentUser;

  public function __construct(CsvEncoder $csvEncoder, EntityTypeManagerInterface $entityTypeManager, AccountInterface $currentUser) {
    $this->csvEncoder = $csvEncoder;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  /**
   * @param string $line
   * @return \DateTime
   */
  private function getStatementStartDate(string $line): \DateTime {
    $line = trim($line, '"');
    $line = str_replace('Date Range : ', '', $line);
    $line = explode('-', $line)[0];
    return \DateTime::createFromFormat('m/d/Y', $line);
  }

  /**
   * @param string $data
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \League\Csv\Exception
   */
  public function import(string $data) {
    $lines = explode("\n", $data);
    $date = $this->getStatementStartDate($lines[2]);
    $node = Node::create([
      'type' => 'statement',
      'field_month' => $date->format('n'),
      'field_year' => $date->format('Y'),
    ]);

    $csv = $this->csvEncoder->decode(implode("\n", array_slice($lines, 4)), 'csv');

    foreach ($csv as $row) {
      $lineItem = Paragraph::create([
        'type' => 'expense',
        'field_tracking_number' => $row[0],
        'field_date' => \DateTime::createFromFormat('m/d/Y', $row[1])->format('Y-m-d'),
        'field_description' => $row[2],
        'field_memo' => $row[3],
        'field_debit' => !empty($row[4]) ? $row[4] : $row[5],
      ]);
      $lineItem->save();
      $node->field_line_items[] = $lineItem;
    }

    $node->save();
  }
}
