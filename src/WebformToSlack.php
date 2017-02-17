<?php

namespace Drupal\webform_slack;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use GuzzleHttp\Client;
use Drupal\webform\Entity\Webform;

/**
 *
 */
class WebformToSlack implements ContainerFactoryPluginInterface {

  /**
   * Logger Object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  private $loggerChannelFactory;

  /**
   * ConfigFactory object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $configFactory;

  /**
   * ClientInterface Object.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(LoggerChannelFactory $loggerChannelFactory, ConfigFactory $configFactory, Client $httpClient) {
    $this->loggerChannelFactory = $loggerChannelFactory->get('slack');
    $this->configFactory = $configFactory->get('webform_slack.settings');
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('http_client')
    );
  }

  /**
   * Gets the list of channel from Slack.
   *
   * @param string $token
   *   OAuth token of slack team.
   *
   * @return array
   *   Array of channels.
   */
  public function getChannelsList($token) {
    $url = 'https://slack.com/api/channels.list?token=' . $token;
    $request = $this->httpClient->get($url);
    $response = json_decode($request->getBody(), TRUE);
    foreach ($response['channels'] as $key => $channel) {
      $list[$channel['name']] = '#' . $channel['name'];
    }
    return $list;
  }

  /**
   * Gets the list of webforms.
   */
  public function getWebformIDs() {
    foreach (Webform::loadMultiple() as $webform_obj) {
      $webforms[$webform_obj->id()] = $webform_obj->label();
    }
    return $webforms;
  }

  /**
   *
   */
  public function sendRequestToSlack($message, $channel) {
    $headers = array(
      'Content-Type' => 'application/x-www-form-urlencoded',
    );
    unset($message['in_draft']);
    $text = '';
    foreach ($message as $field => $data) {
      $text .= "*$field* : `$data` \n";
    }
    $message_body['channel'] = '#' . $channel;
    $message_body['as_user'] = TRUE;
    $message_body['text'] = $text;
    $slack_message = 'payload=' . urlencode(json_encode($message_body));
    $incoming_webhook = $this->configFactory->get('incoming_webhook_url');
    try {
      $response = $this->httpClient->request('POST', $incoming_webhook, array('headers' => $headers, 'body' => $slack_message));
      $this->loggerChannelFactory->info("Message has been sent to slack #$channel!");
    }
    catch (ConnectException $connectException) {
      $this->loggerChannelFactory->error("Connection error! Check your internet connection. Message failed to be send to slack #$channel.");
      watchdog_exception('slack', $connectException);
    }
    catch (RequestException $requestException) {
      $this->loggerChannelFactory->error('Request error! It may appear that you have entered the invalid Webhook url.');
      watchdog_exception('slack', $requestException);
    }
  }

}
