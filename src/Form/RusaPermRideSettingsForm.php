<?php

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for a rusa perm registration entity type.
 */
class RusaPermRideSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rusa_perm_ride_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'rusa_perm_ride.settings',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

		$config = $this->config('rusa_perm_ride.settings');

    $form['settings'] = [
      '#markup' => $this->t('Customize the messages displayed on the Perm Program Registration form.'),
    ];

    $form['instructions'] = [
      '#type'      => 'textarea',
      '#title'     => 'Perm ride Registration form instructions',
      '#rows'      => 6,
      '#cols'      => 40,
      '#resizable' => 'both',
			'#default_value' => $config->get('instructions'), 
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
