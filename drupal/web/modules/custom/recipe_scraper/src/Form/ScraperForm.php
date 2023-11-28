<?php declare(strict_types = 1);

namespace Drupal\recipe_scraper\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\recipe_scraper\IngredientNormalizer;
use Drupal\recipe_scraper\RecipeTransformer;
use Drupal\recipe_scraper\ScraperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Recipe Scraper form.
 */
final class ScraperForm extends FormBase {

  /**
   * @var ScraperInterface
   */
  private $scraper;

  /**
   * @var RecipeTransformer
   */
  private $recipeTransformer;

  public function __construct(ScraperInterface $scraper, RecipeTransformer $recipeTransformer) {
    $this->scraper = $scraper;
    $this->recipeTransformer = $recipeTransformer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recipe_scraper.scraper'),
      $container->get('recipe_scraper.recipe_transformer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'recipe_scraper_scraper';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Send'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if (mb_strlen($form_state->getValue('message')) < 10) {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('Message should be at least 10 characters.'),
    //     );
    //   }
    // @endcode
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    //dpm($form_state->getValues());
    $date = $this->scraper->scrape($form_state->getValue('url'));
    $recipe = $this->recipeTransformer->transform($date);

    dpm($recipe);


    //$this->messenger()->addStatus($this->t('The message has been sent.'));
    //$form_state->setRedirect('<front>');
  }

}
