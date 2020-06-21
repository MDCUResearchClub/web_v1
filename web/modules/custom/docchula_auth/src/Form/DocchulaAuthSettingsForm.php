<?php

namespace Drupal\docchula_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Settings form for Docchula Auth.
 */
class DocchulaAuthSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'docchula_auth_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['docchula_auth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('docchula_auth.settings');

    $form['credential_path'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Credential Path'),
      '#default_value' => $config->get('credentials_path'),
      '#description' => $this->t('Path to JSON credentials.'),
    ];

    $form['hosted_domain'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Hosted Domain'),
      '#default_value' => $config->get('hosted_domain'),
      '#description' => $this->t('The G Suite domain to which users must belong to sign in.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('docchula_auth.settings')
      ->set('credentials_path', trim($values['credentials_path']))
      ->set('hosted_domain', trim($values['hosted_domain']))
      ->save();

    return parent::submitForm($form, $form_state);
  }

}
