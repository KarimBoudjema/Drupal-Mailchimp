<?php

namespace Drupal\kb_mailchimp\Service;

interface MailchimpServiceInterface {

  /**
   * Send the registration form's data to the Mailchimp API.
   *
   * @param null $data
   *  The data of the registration form.
   * @return bool
   *  If the request is successful or not.
   */
  public function sendToMailchimp($data = NULL);


  /**
   * Validate the credentials from the config form.
   *
   * @param $apiKey
   *  The mailchimp API Key.
   * @param $listId
   *  The audience list ID.
   * @return bool|mixed
   *  Return the response body with information on the audience list
   *  Return false if the request is not successful.
   */
  public function validateCredentials($apiKey, $listId);

}
