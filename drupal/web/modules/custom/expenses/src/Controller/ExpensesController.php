<?php declare(strict_types = 1);

namespace Drupal\expenses\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\TermInterface;
use NumberFormatter;

/**
 * Returns responses for Expenses routes.
 */
final class ExpensesController extends ControllerBase {

  /**
   * Eager load the entities we need.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function overviewEntities(): array {
    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'statement')
      ->condition('status', 1)
      ->sort('field_year', 'DESC')
      ->sort('field_numeric_month', 'DESC')
      ->range(0, 12)
      ->execute();
    $nids = array_reverse($nids);

    $tids = $this->entityTypeManager()
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck()
      ->condition('vid', 'expense_categories')
      ->condition('name', ['Ignore'], 'NOT IN')
      ->sort('field_income', 'DESC')
      ->sort('name')
      ->execute();

    $pids = $this->entityTypeManager()
      ->getStorage('paragraph')
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'expense')
      ->condition('parent_id', $nids, 'IN')
      ->execute();

    $categories = $this->entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadMultiple($tids);

    return [
      $this->entityTypeManager()
        ->getStorage('node')
        ->loadMultiple($nids),
      $categories,
      Paragraph::loadMultiple($pids),
    ];
  }

  /**
   * @param NodeInterface[] $statements
   * @param \Drupal\taxonomy\TermInterface[] $categories
   * @param \Drupal\paragraphs\ParagraphInterface[] $expenses
   * @return array
   */
  private function compileData(array $statements, array $categories, array $expenses): array {
    $data = [array_merge(['Categories'], $statements)];

    foreach ($categories as $category) {
      foreach ($statements as $statement) {
        $line_item_values = $statement->field_line_items->getValue();
        $data[$category->id()][''] = $category;
        $data[$category->id()][$statement->id()] = 0;
        foreach ($line_item_values as $line_item_value) {
          if ($expenses[$line_item_value['target_id']]->field_category->target_id == $category->id()) {
            $field = $category->field_income->value ? 'field_debit' : 'field_negative_debit';
            $data[$category->id()][$statement->id()] += $expenses[$line_item_value['target_id']]->{$field}->value;
          }
        }
      }
    }

    return $data;
  }

  /**
   * Overview route callback.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function overview() {
    [$statements, $categories, $expenses] = $this->overviewEntities();
    $data = $this->compileData($statements, $categories, $expenses);
    $build['table'] = $this->buildTable($data);
    $build['chart'] = $this->buildChart($data);

    return $build;
  }

  /**
   * Build renderable array.
   *
   * @param array $data
   * @return array
   */
  private function buildTable(array $data): array {
    $table = [
      '#type' => 'table',
      '#header' => array_map(function ($item) {
        if ($item instanceof EntityInterface) {
          return $item->toLink(str_replace('Statement: ', '', $item->label()));
        }
        return $item;
      }, array_shift($data)),
      '#attributes' => ['class' => ['views-table']],
    ];
    $table['#header'][] = 'Avg.';

    $fmt = numfmt_create('en_US', NumberFormatter::CURRENCY);
    foreach ($data as $id => $row) {
      $table['#rows'][$id][-1] = array_shift($row)->toLink();
      $total = 0;
      $table['#rows'][$id] += array_map(function ($cell) use ($fmt, &$total) {
        $total += $cell;
        return ['data' => numfmt_format_currency($fmt, floatval($cell), 'USD')];
      }, $row);
      $avg = $total / (count($row) -1);
      $table['#rows'][$id][] = numfmt_format_currency($fmt, $avg, 'USD');

    }

    return $table;
  }

  /**
   * @param array $data
   * @return array
   */
  private function buildChart(array $data): array {
    $charts_container = [
      '#type' => 'container',
      'content' => [],
    ];

    $headers = array_shift($data);
    array_shift($headers); // Remove label.
    $charts_container['content']['real'] = [
      '#type' => 'chart',
      '#title' => 'My title',
      '#chart_type' => 'line',
      'x_axis' => [
        '#type' => 'chart_xaxis',
        '#title' => $this->t('Months'),
        '#labels' => array_map(function (EntityInterface $item) {
          return str_replace('Statement: ', '', $item->label());
        }, $headers),
      ],
      'y_axis' => [
        '#type' => 'chart_yaxis',
        '#title' => 'Amount',
      ],
      '#raw_options' => [
        'options' => [
          'height' => '1000',
          'width' => '1280',
          'width_units' => '%',
        ]
      ],
    ];

    foreach (array_values($data) as $row_num => $row) {
      if (reset($row)->field_income->value) {
        continue;
      }

      $charts_container['content']['real']["series_{$row_num}"] = [
        '#type' => 'chart_data',
        '#title' => array_shift($row)->label(),
        '#data' => array_values($row),
      ];
    }

    return $charts_container;
  }
}
