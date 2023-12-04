<?php

namespace Drupal\expenses\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
final class ExpensesCommands extends DrushCommands {

  /**
   * Constructs an ExpensesCommands object.
   */
  public function __construct(
    private readonly Token $token,
    private readonly EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'expenses:export', aliases: ['foo'])]
  #[CLI\Usage(name: 'expenses:export', description: 'Usage description')]
  public function commandName() {
    $statements = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => 'statement']);

    $data = [];
    foreach ($statements as $statement) {
      $line_items = $statement->field_line_items->referencedEntities();
      foreach ($line_items as $line_item) {
        $data[] = [
          'tracking_number' => $line_item->field_tracking_number->value,
          'date' => $line_item->field_date->value,
          'description' => $line_item->field_description->value,
          'memo' => $line_item->field_memo->value,
          'amount' => $line_item->field_debit->value,
          'category' => $line_item->field_category->entity ? $line_item->field_category->entity->label() : '',
        ];
      }
    }

    echo json_encode($data);
  }

  /**
   * An example of the table output format.
   */
  #[CLI\Command(name: 'expenses:token', aliases: ['token'])]
  #[CLI\FieldLabels(labels: [
    'group' => 'Group',
    'token' => 'Token',
    'name' => 'Name'
  ])]
  #[CLI\DefaultTableFields(fields: ['group', 'token', 'name'])]
  #[CLI\FilterDefaultField(field: 'name')]
  public function token($options = ['format' => 'table']): RowsOfFields {
    $all = $this->token->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }

}
