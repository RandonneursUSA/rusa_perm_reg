<?php

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for a rusa perm registration entity type.
 */
class RusaSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rusa_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'rusa.settings',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

		$config = $this->config('rusa.settings');

    $form['settings'] = [
      '#markup' => $this->t('Generic RUSA settings form.'),
    ];

    $form['rusa'] = [
        '#type'   => 'textfield',
        '#title'  => t('Site name'),
        '#length' => 80,
        '#default_value' => t('Randonneurs USA'),
     ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

     $values = $form_state->getValues();

     $this->config('rusa_perm_ride.settings')
           ->set('instructions', $values['instructions'])
           ->save();

    parent::submitForm($form, $form_state);
  }

}
