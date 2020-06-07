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
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Messenger;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rusa_perm_reg\RusaRegData;
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
  protected $entityTypeManager;
  protected $uinfo;
  protected $regdata;

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
  public function __construct(AccountProxy $current_user, EntityTypeManagerInterface $entityTypeManager) {
    $this->currentUser = $current_user;
    $this->messenger   = \Drupal::messenger();
		$this->entityTypeManager = $entityTypeManager;
    $this->uinfo = $this->get_user_info();
    $this->regdata = new RusaRegData();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
			$container->get('entity_type.manager'),
    );
  }

  /**
   * @buildForm
   *
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {  

    // Start the form
    $form['prog'] = [
      '#type'   => 'details',
      '#title'  => t('Program Registration'),
      '#group'  => 'perm',
    ];

    // Display member name and # so they know who they are
		$form['prog']['user'] = [
			'#type'		=> 'item',
			'#markup' => t($this->uinfo['name'] . ' RUSA #' . $this->uinfo['mid']),
		];

    // Does registration exist
    if ($this->regdata->reg_exists()) {
     
      // Get registration id
      $regid = $this->regdata->get_reg_id();

      // Get registration dates
      $regdates = $this->regdata->get_reg_dates();
	
      // Does the waiver exists?
      if ($this->regdata->waiver_exists()) {
      
        // Is Waiver expires
        if ($this->regdata->waiver_expired()) {
          // This will probably be a new registration
          $form['prog']['waiver'] = [
            '#type'   => 'item',
            '#markup' => t('Your signed waiver is expired. You need to upload a new one..'),
          ];
        }
      }
      else {
        // Waiver does not exists
      
        // Upload link for waiver
        $form['prog']['waiver'] = [
          '#type'   => 'item',
          '#markup' => t('Your perm program registaion is not complete. You still need to upload your signed waiver.'),
        ];
      }

      // Has payment been received :
      if (! $this->regdata->payment_received()) {
        $form['prog']['payment'] = [
          '#type'   => 'item',
          '#markup' => t('Your perm program registaion is not complete. You still need to pay the fee.'),
        ];
      }
      
      // Has registration been approved
      $approver = $this->regdata->registration_approved();
      if (! $approver ) {
        $form['prog']['approval'] = [
          '#type'   => 'item',
          '#markup' => t('Your perm program registaion complete and waiting for approval.'),
        ];
      }
    
      else {
        // Registration is complete so show it

        // Build a link to view the registration
		    $reg_link = Link::fromTextAndUrl('Current perm program registration', Url::fromUri('internal:/rusa_perm_registration/' . $regid))->toString();
        $active_reg = $regdates[0] . ' to ' . $regdates[1];

        $form['prog']['curreg'] = [
          '#type'   => 'item',
          '#markup' => t('Your ' . $reg_link . ' is valid from ' . $active_reg . ' Approved by: ' . $approver ),
        ];
      }
    }
    else {
      // Display the form to create a new registration.

      // Get instructions from our config settings
      $instructions = \Drupal::config('rusa_perm_reg.settings')->get('instructions');

		  // Process steps
      $steps = [
			  'Download Release Form (logic is added here to detect the current release form and make this download link)', 
			  'Print, date, sign, and scan the release form.(maybe add a link for some tips on how to scan and prepare for upload)',
			  'Upload your signed waiver (this will be a file upload field)', 
			  'Pay annual fee (this will be a link to the store payment form)',
		  ];


      $form['prog']['info'] = [
        '#type'   => 'item',
        '#markup' => t('<em>There will be logic here. If the user already has a valid program registration they will see that. ' .
										 'Else if they have a pending registration they will see that. ' .
                     'Else they will see the process below.</em>'),
      ];


      $form['prog']['reg'] = [
        '#type'   => 'item',
        '#markup' => t($instructions),
      ];

	  	$form['prog']['steps'] = [
   	  	'#theme' => 'item_list',
        '#list_type' => 'ul',
        '#title' => 'Registration Steps',
        '#items' => $steps,
        '#attributes' => ['class' => ['rusa-list']],
		  ];

  }

    
/* I'm not sure we'll need to actually submit this form.

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

  /** 
   * Get user info
   *
   */
  protected function get_user_info() {
		$user_id   = $this->currentUser->id(); 
    $user      = User::load($user_id);

    $uinfo['uid'] = $user_id;
		$uinfo['name'] = $user->get('field_display_name')->getValue()[0]['value'];
    
    // Get the user's RUSA #
    $uinfo['mid'] = $user->get('field_rusa_member_id')->getValue()[0]['value'];
    return($uinfo);
 }


} // End of class  
