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

        // Build the form

        $form['rusa'] = [
            '#type'        => 'vertical_tabs',
            '#default_tab' => 'settings',
        ];

        $form['settings'] = [
            '#type'   => 'details',
            '#title'  => $this->t('Settings'),
            '#group'  => 'rusa',
        ];
        
        // SmartWaiver URL
        $form['settings']['swurl'] = [
            '#type'     => 'textfield',
            '#title'    => $this->t('SmartWaiver URL'),
            '#size'     => 40,
            '#default_value' => $config->get('swurl'),
        ];
        
        // SmartWaiver API Key
        $form['settings']['api_key'] = [
            '#type' => 'key_select',
            '#title' => $this->t('Smartwaiver API Key'),
            '#default_value' => $config->get('api_key'),
        ];

        // SmartWaiver Webhook key
        $form['settings']['webhook_key'] = [
            '#type' => 'key_select',
            '#title' => $this->t('Smartwaiver Webhook Private Key'),
            '#default_value' => $config->get('webhook_key'),
        ];
        
        
        // Messages
        $form['messages'] = [
            '#type'   => 'details',
            '#title'  => $this->t('Messages'),
            '#group'  => 'rusa',
        ];
        

        $form['messages']['instructions'] = [
            '#type'          => 'textarea',
            '#title'         => $this->t('Perm ride registration form instructions'),
            '#rows'          => 6,
            '#cols'          => 40,
            '#resizable'     => 'both',
            '#default_value' => $config->get('instructions'), 
        ];

        $form['messages']['search'] = [
            '#type'           => 'textarea',
            '#title'          => $this->t('Search link text'),
            '#description'    => $this->t('Enter the text to accompany the link to the perm search form.'),
            '#rows'           => 4,
            '#cols'           => 40,
            '#resizable'      => 'both',
            '#default_value'  => $config->get('search'), 
        ];


        $form['messages']['route'] = [
            '#type'           => 'textarea',
            '#title'          => 'Route field text',
            '#description'    => $this->t('Enter the text to accompany route # field'),
            '#rows'           => 4,
            '#cols'           => 40,
            '#resizable'      => 'both',
            '#default_value'  => $config->get('route'), 
        ];

        $form['messages']['sr'] = [
            '#type'           => 'textarea',
            '#title'          => 'SR error message ',
            '#description'    => $this->t('Enter the error text they see if the enter thr route # of and SR'),
            '#rows'           => 4,
            '#cols'           => 40,
            '#resizable'      => 'both',
            '#default_value'  => $config->get('sr'),                         
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
                ->set('swurl',        $values['swurl'])
                ->set('api_key',      $values['api_key'])
                ->set('webhook_key',  $values['webhook_key'])
                ->set('instructions', $values['instructions'])
                ->set('search',       $values['search'])
                ->set('route',        $values['route'])
                ->set('sr',           $values['sr'])
                ->save();

        parent::submitForm($form, $form_state);
    }

}
