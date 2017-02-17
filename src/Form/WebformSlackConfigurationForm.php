<?php

namespace Drupal\webform_slack\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\webform_slack\WebformToSlack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WebformSlackConfigurationForm.
 */
class WebformSlackConfigurationForm extends ConfigFormBase {

  /**
   * WebformToSlack services object.
   *
   * @var \Drupal\webform_slack\WebformToSlack
   */
  private $webformToSlack;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, WebformToSlack $webformToSlack) {
    parent::__construct($config_factory);
    $this->webformToSlack = $webformToSlack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('webform_slack.services')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_slack_configurations';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['webform_slack.settings'];
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('webform_slack.settings');

    $form['incoming_webhook_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Incoming Webhook URL'),
      '#description' => $this->t('Enter your Incoming Webhook URL. See @webhooks', [
        '@webhooks' => Link::fromTextAndUrl('Incoming Webhooks', Url::fromUri('https://api.slack.com/incoming-webhooks'))->toString(),
      ]),
      '#default_value' => $config->get('incoming_webhook_url'),
      '#required' => TRUE,
    ];
    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OAuth token'),
      '#description' => $this->t('Enter your OAuth token. See @tokens', [
        '@tokens' => Link::fromTextAndUrl('Tokens for testing', Url::fromUri('https://api.slack.com/docs/oauth-test-tokens'))->toString(),
      ]),
      '#default_value' => $config->get('token'),
      '#required' => TRUE,
    ];
    $form['webforms_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Webforms-Slack Channel Configuration'),
      '#open' => FALSE,
      '#description' => $this->t('Choose the webforms on whose submission the message will be sent to appropriate select channel.'),
    ];
    $form['webforms_list']['list'] = [
      '#type' => 'table',
      '#header' => [$this->t('Webform'), $this->t('Slack Channel')],
    ];
    $channels = $this->webformToSlack->getChannelsList($config->get('token'));
    $webforms = $this->webformToSlack->getWebformIDs();
    foreach ($webforms as $webform_id => $webform_name) {
      $form['webforms_list']['list'][$webform_id]['webform'] = [
        '#type' => 'checkbox',
        '#title' => $webform_name,
        '#return_value' => $webform_id,
        '#default_value' => $config->get($webform_id)['webform'],
      ];
      $form['webforms_list']['list'][$webform_id]['channel'] = [
        '#type' => 'select',
        '#options' => !empty($channels) ? $channels : [
          'general' => '#general',
        ],
        '#default_value' => $config->get($webform_id)['channel'],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('webform_slack.settings');
    $values = $form_state->getValues();
    $config->set('incoming_webhook_url', $values['incoming_webhook_url'])
      ->set('token', $values['token'])
      ->save();
    foreach ($values['list'] as $webform_id => $slack_channel) {
      $config->set($webform_id, $slack_channel)->save();
    }
    parent::submitForm($form, $form_state);
  }

}
