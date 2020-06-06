<?php

/**
 * @file
 *  RusaPermRegForm.php
 *
 * @Created 
 *  2020-06-05 - Paul Lieberman
 *
 * RUSA Perms Registration Form
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Messenger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RusaMemberEditForm
 *
 * This is the Drupal Form class.
 * All of the form handling is within this class.
 *
 */
class RusaPermRegForm extends FormBase {

  protected $currentUser;
  protected $messenger;

  /**
   * @getFormID
   *
   * Required
   *
   */
  public function getFormId() {
    return 'rusa_perm_reg_form';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $current_user) {
    $this->currentUser = $current_user;
    $this->messenger   = \Drupal::messenger();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * @buildForm
   *
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {  
   
    // Get logged in user's RUSA ID
    $user = User::load($this->currentUser->id());
    $mid  = $user->get('field_rusa_member_id')->getValue()[0]['value'];

    if (empty($mid)) {
      // This should never happen as they should get an access denied first
      $this->messenger->addMessage(t("You must be logged in and have a RUSA # to use this form."), $this->messenger::TYPE_ERROR);
      $form_state->setRedirect('rusa_home');
      return;
    }

    // Build the form
    $form['perm'] = [
      '#type'        => 'vertical_tabs',
      '#default_tab' => 'prog',
    ];

    $form['prog'] = [
      '#type'   => 'details',
      '#title'  => t('Program Registration'),
      '#group'  => 'perm',
    ];

    $form['prog']['info'] = [
      '#type'   => 'item',
      '#markup' => t('If already registered for the program we will show that here with the expiration date. Else we show what follows'),
    ];

    $form['prog']['reg'] = [
      '#type'   => 'item',
      '#markup' => t('Before you can register to ride a permanent you must register for the program.<br />' .
                     'The registration is a several step proccess:'),
    ];

		$form['prog']['steps'] = [
   		'#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => 'Registration Steps',
      '#items' => ['Download Release Form', 'Print, date, sign, scan, and upload the release form', 'Pay annual fee'],
      '#attributes' => ['class' => ['rusa-list']],
		];


    $form['ride'] = [
        '#type'   => 'details',
        '#title'  => t('Ride Registration'),
        '#group'  => 'perm',
     ];

     $form['ride']['info'] = [
      '#type'   => 'item',
      '#markup' => t('Instructions for ride registration go here')
    ];

     
/*
   // Actions wrapper
    $form['actions'] = [
      '#type'   => 'actions',

      'cancel'  => [
        '#type'  => 'submit',
        '#value' => 'Cancel',
        '#attributes' => ['onclick' => 'if(!confirm("Do you really want to cancel?")){return false;}'],
      ],
      'submit' => [
        '#type'  => 'submit',
        '#value' => 'Submit',
      ],
   ];
*/
    // Attach the Javascript and CSS, defined in rusa_rba.libraries.yml.
    // $form['#attached']['library'][] = 'rusa_api/chosen';
    $form['#attached']['library'][] = 'rusa_api/rusa_script';
    $form['#attached']['library'][] = 'rusa_api/rusa_style';

    return $form;
  }

 /**
   * @validateForm
   *
   * Required
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  } // End function verify


  /**
   * @submitForm
   *
   * Required
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getTriggeringElement();
    if ($action['#value'] == "Cancel") {
      $form_state->setRedirect('rusa_home');
    }
    else {
      $this->messenger->addMessage(t("Your changes have been saved."), $this->messenger::TYPE_STATUS);
    }
  }

} // End of class  
