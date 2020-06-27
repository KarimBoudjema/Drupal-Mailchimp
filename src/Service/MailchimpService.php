<?php

namespace Drupal\kb_mailchimp\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Class MailchimpService.
 *
 * @package Drupal\kb_mailchimp\Service
 */
class MailchimpService {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Maichimp API Key.
   *
   * @var string|null
   */
  protected $apiKey;

  /**
   * The Mailchimp Audience list ID.
   *
   * @var string|null
   */
  protected $listId;

  /**
   * The log service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  private $loggerFactory;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;


  /**
   * The main constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\Core\Logger\LoggerChannelFactory $factory
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(ClientInterface $http_client,
                              ConfigFactory $config_factory,
                              LoggerChannelFactory $factory,
                              MessengerInterface $messenger
) {
    $this->httpClient = $http_client;

    // Get the credentials.
    $config = $config_factory->get('mailchimp_credentials.config');
    $this->apiKey = $config->get('mailchimp_api_key');
    $this->listId = $config->get('mailchimp_list_id');

    $this->loggerFactory = $factory;
    $this->messenger = $messenger;
  }

  /**
   * Send the registration form's data to the Mailchimp API.
   *
   * @param null $data
   * @return bool
   */
  public function sendToMailchimp($data = NULL) {

    // Check if we have data to send.
    if (empty($data)){
      // return silently.
      $this->logErrorMessage('No data to send');
      return false;
    }

    // Define the endpoint with the datacenter and the audience list.
    $endpoint = '/lists/'.$this->listId.'/members';

    // Define the body with the data coming from the user data.
    $body = $this->prepareBody($data);

    // Json encode the body.
    $body = json_encode($body);

    // Define the auth, body and query to send to Maichimp.
    $options = [
      'auth' => ['anystring',$this->apiKey],
      'body' => $body,
      'query' => NULL
    ];

    $request = $this->connect($this->apiKey, $endpoint, $options, 'post',1);

    return ($request) ? TRUE : FALSE;

  }

  /**
   * Connect to the Mailchimp API.
   *
   * @param $apiKey
   * @param $endpoint
   * @param $options
   *
   * @param bool $showErrors
   *
   * @return bool
   */
  public function connect($apiKey, $endpoint, $options, $method, $showErrors = false) {

    try{
      // Extract the datacenter (us1, us2, us3 ... ) from the API Key.
      $dataCenter = $this->getDataCenter($apiKey);

      // Define the URI.
      $url = 'https://'.$dataCenter.'.api.mailchimp.com/3.0'. $endpoint;

      // Call the mailchimp API and get the response.
      $response = $this->httpClient->{$method}($url,$options);

      if ($response->getStatusCode() == '200'){

        return $response;

      }
    }
    catch (RequestException $exception){
      $errorContent = json_decode($exception->getResponse()->getBody()->getContents());
      // Log the error.
      $this->logErrorMessage($errorContent->detail. ' - '.$exception->getMessage());
      // Show the error to the user if needed.
      ($showErrors) ? $this->showErrors($exception, $errorContent) : NULL;
      return FALSE;
    }

    catch (Exception $e) {
      $this->logErrorMessage($e->getMessage());
      ($showErrors) ? $this->showErrors($e) : NULL;
      return FALSE;
    }

  }

  /**
   * Show to the user the error and code message.
   *
   * @param $e
   * @param null $errorContent
   */
  private function showErrors($e, $errorContent = NULL){

    // Show a human readable error messages if we can.
    if (!is_null($errorContent)) {
      if ($errorContent->title == 'Member Exists') {
        $this->messenger->addWarning("It seems that you're already subscribed.");
      }
      else {
        $this->messenger->addError($errorContent->title . ' ' . $errorContent->detail);
      }

    }
    else {
      $this->messenger->addError($e->getMessage());
      $this->messenger->addError('Error Code: '.$e->getCode());
    }

  }

  /**
   * Prepare the body with data to send to mailchimp.
   *
   * Remember that to send other fields than 'email_address' and 'status'
   * they have to be sent in the 'merge_fields' sub array.
   *
   * @param $data
   * @return array
   */
  protected function prepareBody($data){

    // Construct the body array to send to Mailchimp.
    $body = [];

    // Add the email and the subscribed status.
    $body['email_address'] = $data['email'] ? $data['email'] : '';
    $body['status'] = 'subscribed';

    // Add other fields into the merge_fields sub array.
    if (isset($data['first_name'])){
      $body['merge_fields']['FNAME'] = $data['first_name'];
    }

    if (isset($data['last_name'])){
      $body['merge_fields']['LNAME'] = $data['last_name'];
    }

    if (isset($data['zip_code'])){
      $body['merge_fields']['ZIP'] = $data['zip_code'];
    }

    return $body;

  }

  /**
   * Log errors into the kb_mailchimp logger.
   *
   * @param string|null $error
   */
  private function logErrorMessage(string $error = NULL){

    $error = is_null($error) ? 'Unknown error' : $error;

    $this->loggerFactory
      ->get('kb_mailchimp')
      ->error(" Failed to complete Mailchimp call: %error",
        ['%error' => $error]);

  }

  /**
   * Extract the datacenter from the API Key.
   *
   * @param string $apiKey
   * @return bool|mixed|string
   *
   * @throws Exception
   */
  private function getDataCenter(string $apiKey) {

    // Check if we have a - in the API Key.
    if (strpos($apiKey, '-') === false) {
      throw new Exception("Unable to extract DataCenter from API Key");
    }

    // Extract the data center from the API Key.
    $dcParts = explode('-', $apiKey);
    return isset($dcParts[1]) ? $dcParts[1] : 'us1';

  }

  /**
   * Validate the credentials from the config form.
   *
   * @param $apiKey
   * @param $listId
   *
   * @return bool|mixed
   */
  public function validateCredentials($apiKey, $listId) {

    try {

      // Extract the datacenter (us1, us2, us3 ... ) from the API Key.
      $dataCenter = $this->getDataCenter($apiKey);

      // Define the URI with the datacenter and the audience list.
      $uri = 'https://'.$dataCenter.'.api.mailchimp.com/3.0/lists/'.$listId;

      // Define Api Key authentication and fields to retrieve.
      $options = [
        'auth' => ['anystring',$apiKey],
        'query' => ['fields' => 'name,id,stats']
      ];

      // Get the response.
      $response = $this->httpClient->get($uri, $options);

      return json_decode($response->getBody()->__toString());

    }
      // In this case, we don't catch the RequestException because there's still
      // a bug -> https://github.com/guzzle/guzzle/issues/1959
      // We just catch the simple Guzzle ClientException.
    catch (Exception $e) {

      if (get_class($e) == 'GuzzleHttp\Exception\ClientException') {
        $errorContent = json_decode($e->getResponse()->getBody()->getContents());
        $errorMsg =   $errorContent->title . ". " .
          $errorContent->detail .
          " Error code: " . $errorContent->status;
        // Log error.
        $this->logErrorMessage($errorMsg);
        // Show error messages.
        $this->messenger->addError($errorMsg);
      }
      else {
        // Log error.
        $this->logErrorMessage($e->getMessage());
        // Show error messages.
        $this->messenger->addError($e->getMessage());
        $this->messenger->addError('Error Code: '.$e->getCode());
      }

      return false;

    }

  }

}
