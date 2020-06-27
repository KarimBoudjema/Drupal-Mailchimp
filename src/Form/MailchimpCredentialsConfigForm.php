<?php

namespace Drupal\kb_mailchimp\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\kb_mailchimp\Service\MailchimpService;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class MailchimpCredentialsConfigForm.
 *
 * This form stores the Newsletter (mailchimp) API credentials and other config.
 *
 */
class MailchimpCredentialsConfigForm extends ConfigFormBase {


  /**
   * Success response object when validating the credentials.
   * @var object
   */
  private $successResponse;

  /**
   * The mailchimp service.
   *
   * @var \Drupal\kb_mailchimp\Service\MailchimpService
   */
  private $mailchimpService;

  public function __construct(ConfigFactoryInterface $config_factory,
                              MailchimpService $mailchimpService) {
    parent::__construct($config_factory);
    $this->mailchimpService = $mailchimpService;
  }

  public static function create(ContainerInterface $container) {

    // As we inject a new service kb_mailchimp.service, we don't
    // return parent::create($container);
    // The parent only get $container->get('config.factory') out of
    // the container. So we also add it here.
    return new static(
      $container->get('config.factory'),
      $container->get('kb_mailchimp.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    // Get or create the configuration object name.
    return [
      'mailchimp_credentials.config',
    ];
  }

	/**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailchimp_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get the value stored in the config.
    $config = $this->config('mailchimp_credentials.config');

    $form['intro'] = array(
      '#type' => 'item',
      '#markup' => $this->t(
        'This form stores the Mailchimp API credentials and some form
        configurations.'
      ),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    );

    // Mailchimp fieldset.
    $form['maichimp_fieldset'] = [
      '#type'        => 'details',
      '#title'       => t('Maichimp credentials'),
      '#open'        => TRUE,
    ];

    // Define the Mailchimp API key.
    $form['maichimp_fieldset']['mailchimp_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $config->get('mailchimp_api_key'),
      '#size' => 80,
      '#required' => TRUE,
    ];

    // Define the Mailchimp list ID.
    $form['maichimp_fieldset']['mailchimp_list_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Audience list ID'),
      '#default_value' => $config->get('mailchimp_list_id'),
      '#size' => 80,
      '#required' => TRUE,
    ];

    // Content fieldset.
    $form['content_fieldset'] = [
      '#type'        => 'details',
      '#title'       => t('Content settings for the registration form.'),
      '#open'        => TRUE,
    ];

    // Define the button label of the registration form.
    $form['content_fieldset']['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Label of the submit button"),
      '#default_value' => $config->get('button_label'),
      '#size' => 40,
      '#required' => TRUE,
    ];

    // Define the thank you message of the registration form.
    $content_thank_you = $config->get('thank_you');
    $form['content_fieldset']['thank_you'] = [
      '#type' => 'text_format',
      '#title' => $this->t("Thank You message"),
      '#description' => $this->t(
        'Text to display to the user after subscribing.'
      ),
      '#default_value' => $content_thank_you['value'],
      '#format' => $content_thank_you['format'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */

  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Check if we have a valid response from the mailchimp API.
    if (!$response = $this->mailchimpService->validateCredentials(
      $form_state->getValue('mailchimp_api_key'),
      $form_state->getValue('mailchimp_list_id')
      )) {
      $form_state->setErrorByName(
        'mailchimp_api_key',
        $this->t('')
      );
      $form_state->setErrorByName(
        'mailchimp_list_id',
        $this->t('The API key or the Audience list ID are not correct.')
      );
    }
    else {
      // Successfully connected. Get the body response with name and stats.
      $this->successResponse = $response;
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Show a success message with some data of the Audience.
    $this->messenger()->addMessage(
      $this->t("Successfully connected to Audience:
      @name (@id) with @total members.",
      ['@name' => $this->successResponse->name,
        '@id' => $this->successResponse->id,
        '@total' => $this->successResponse->stats->member_count
      ])
    );

    // Save the form's value to the config variable.
    $this->config('mailchimp_credentials.config')
	    ->set('mailchimp_api_key', $form_state->getValue('mailchimp_api_key'))
      ->set('mailchimp_list_id', $form_state->getValue('mailchimp_list_id'))
      ->set('button_label', $form_state->getValue('button_label'))
      ->set('thank_you', $form_state->getValue('thank_you'))

      // At this stage, we are sure credentials are OK.
      ->set('mailchimp_checked', 1)

      ->save();

    parent::submitForm($form, $form_state);
  }

}
