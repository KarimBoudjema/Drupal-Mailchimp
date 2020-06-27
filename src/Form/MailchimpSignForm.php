<?php

namespace Drupal\kb_mailchimp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\kb_mailchimp\Service\MailchimpService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class MailchimpSignForm.
 *
 * The main sign-up form.
 *
 * @package Drupal\kb_mailchimp\Form
 */
class MailchimpSignForm extends FormBase {

  /**
   * The mailchimp service.
   *
   * @var \Drupal\kb_mailchimp\Service\MailchimpService
   */
  private $mailchimpService;

  /**
   * The renderer service to turn a render array into a HTML string.
   *
   * @var Renderer
   */
  private $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(MailchimpService $mailchimpService,
                              Renderer $renderer) {

    $this->mailchimpService = $mailchimpService;
    $this->renderer = $renderer;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('kb_mailchimp.service'),
      $container->get('renderer')
    );

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'mailchimp_sign_form';

  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get the value stored in the config.
    $config = $this->config('mailchimp_credentials.config');
    $thank_you = $config->get('thank_you');
    $button_label = $config->get('button_label');

    // Check if we already have mailchimp API configured. If not, stop here.
    if (!$config->get('mailchimp_checked')){
      return $this->getNoCredentialForm();
    }

    // Create a unique form wrapper for ajax.
    $form_wrapper_id = Html::getUniqueId('submit-form-wrapper');
    $form['#prefix'] = '<div id="' . $form_wrapper_id . '">';
    $form['#suffix'] = '</div>';

    // If we already successfully submitted the form,
    // display the thank you message.

    // Get the storage to access the values of the form.
    $storage = $form_state->getStorage();
    $submitSuccess = isset($storage['submitSuccess']) ?
                      $storage['submitSuccess'] : NULL;

    if ($submitSuccess) {

      // Build the thx message.
      $thank_you['value'] = !empty($thank_you['value'])
        ? $thank_you['value'] : 'Thank you for joining our mailing list.';

      // Build the form.
      $form['description'] = [
        '#type' => 'item',
        '#markup' => check_markup($thank_you['value'], $thank_you['format']),
        '#prefix' => '<div class="msg-thank-you">',
        '#suffix' => '</div>',
      ];

      // Do not cache this form.
      $form['#cache'] = ['max-age' => 0];
      return $form;

    }

    // If the form was NOT successfully submitted.
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('First Name *'),
      ],
      '#required' => TRUE,
      '#default_value' => 'Karim',
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('Last Name *'),
      ],
      '#required' => TRUE,
      '#default_value' => 'Boudjema',
    ];

    // Email.
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('Email Address *'),
      ],
      '#required' => TRUE,
      '#default_value' => 'karim@gmail.com',
    ];

    $form['zip_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Zip Code'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('Zip Code *'),
      ],
      '#required' => TRUE,
      '#default_value' => '11111',
    ];

    // Add a ajax submit button that handles the submission of the form.
    $button_label = (!empty($button_label)) ? $button_label : 'Submit';

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#ajax' => [
        'callback' => '::mailchimpSubmitCallback',
        'wrapper' => $form_wrapper_id,
      ],
      '#value' => $this->t($button_label),
    ];

    // Do no cache this form.
    $form['#cache'] = ['max-age' => 0];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Simple example of validation.
    if ($zip = $form_state->getValue('zip_code')) {
      if (strlen($zip) < 5) {
        // Set an error for the zip field.
        $form_state->setErrorByName(
          'zip_code',
          $this->t('Please enter a correct ZIP Code.')
        );
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form.
    $data = $form_state->getValues();

    // Submit to mailchimp. If ok, we rebuild the form with success message.
    if ($this->mailchimpService->sendToMailchimp($data)) {
      $form_state->set('submitSuccess', TRUE);
      $form_state->setRebuild();
    }
    else {
      // Display an general error message to the client.
      // The real errors are logged into the kb_mailchimp channel.
      $this->messenger()->addError($this->t(
        'An error occurred. Your subscription has not been completed.')
      );
    }

  }

  /**
   * Submit Ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Renderable array (the box element)
   */
  public function mailchimpSubmitCallback(array &$form,
                                          FormStateInterface $form_state) {

    // Take all messages and render them in the prefix of the form.
    if ($form_state->hasAnyErrors() || !empty($this->messenger()->all())) {
      $status_messages = ['#type' => 'status_messages'];
      $form['#prefix'] .= $this->renderer->renderRoot($status_messages);
    }

    // Now we can return the form with messages in prefix if they exist.
    return $form;
  }

  /**
   * Generate a form when we don't already have the mailchimp credentials
   * configured.
   *
   * @return mixed
   */
  private function getNoCredentialForm() {

    // Get the url of the config form route in case we changed the path.
    $url = Url::fromRoute('mailchimp_credentials.config_form');
    $link = Link::fromTextAndUrl(t(
      'Go to the Mailchimp configuration form'), $url);

    // Display the message.
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'No Mailchimp API credentials found. @link',
        ['@link' => $link->toString()]
      ),
    ];

    // Do not cache this form.
    $form['#cache'] = ['max-age' => 0];
    return $form;

  }

}
