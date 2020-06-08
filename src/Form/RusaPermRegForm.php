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
use Drupal\file\Entity\File;
use Drupal\media\entity\Media;
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

  protected $settings;
  protected $currentUser;
  protected $messenger;
  protected $entityTypeManager;
  protected $uinfo;
  protected $regdata;
  protected $release_form;

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
    $this->settings = \Drupal::config('rusa_perm_reg.settings');
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
      '#type'   => 'fieldset',
      '#title'  => t('Program Registration'),
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
      
        // Is Waiver expired
        if ($this->regdata->waiver_expired()) {
          // This will probably be a new registration
          $form['prog']['waiver'] = [
            '#type'   => 'item',
					  '#markup' => t($this->settings->get('expired')),
          ];
        }
      }
      else {
        // Waiver does not exists
      
        // Upload link for waiver
        $form['prog']['waiver'] = [
          '#type'   => 'item',
					'#markup' => t($this->settings->get('no_waiver')),
        ];
      }

      // Has payment been received :
      if (! $this->regdata->payment_received()) {
        $form['prog']['payment'] = [
          '#type'   => 'item',
					'#markup' => t($this->settings->get('no_payment')),
        ];
      }
      
      // Has registration been approved
      $approver = $this->regdata->registration_approved();
      if (! $approver ) {
        $form['prog']['approval'] = [
          '#type'   => 'item',
					'#markup' => t($this->settings->get('no_approval')),
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

				$form['prog']['ride'] = [
					'#type' 	=> 'item',
					'#markup' => t($this->settings->get('good_to_go')),
				];
      }
    }
    else {
      // Display the form to create a new registration.

      // Get release form URI and build a link
      $this->release_form  = $this->regdata->get_release_form();
      $url = file_create_url($this->release_form['uri']);
		  $release_link = Link::fromTextAndUrl('Dowload Release Form', Url::fromUri($url,['attributes' => ['target' => '_blank']]))->toString();

		  // Process steps
      $steps = [
        $release_link,
			  'Print, date, sign, and scan the release form.(maybe add a link for some tips on how to scan and prepare for upload)',
			  'Upload your signed release form here.', 
			  'When you submit this form your will be redirected to our payment portal to pay your annual fee.',
		  ];


      $form['prog']['reg'] = [
        '#type'   => 'item',
        '#markup' => t($this->settings->get('instructions')),
      ];

	  	$form['prog']['steps'] = [
   	  	'#theme' => 'item_list',
        '#list_type' => 'ul',
        '#title' => 'Registration Steps',
        '#items' => $steps,
        '#attributes' => ['class' => ['rusa-list']],
		  ];

      $form['prog']['waiver'] = [
        '#type' 						=> 'managed_file',
        '#title' 						=> t('Upload your signed release form.'),
    		'#upload_location'  => 'public://waivers/' . $this->uinfo['mid'] . '/',
    		'#multiple'         => FALSE,
    		'#description'      => t('Allowed extensions: pdf png jpg jpeg'),
    		'#upload_validators'    => [
      		'file_validate_extensions'    => array('pdf png jpg jpeg'),
      		'file_validate_size'          => array(25600000)
    		],
      ];

    
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
	}
    // Attach the Javascript and CSS, defined in rusa_api.libraries.yml.
    // $form['#attached']['library'][] = 'rusa_api/rusa_script';
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

    if (! $form_state->getValue('waiver')){
			$form_state->setError($form['waiver'], t('You have not uploaderd your signed waiver.'));
		}

    // $form_state->setRebuild();
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
      $form_state->setRedirect('user.page');
    }
    else {
			// First we create a media entity from the uploaded file
      // Then we can create the registration entity

    	// Get the fid of the uploaded waiver
    	$fid = $form_state->getValue('waiver')[0];
 
			// Now we can create a media entity
			$media = Media::create([
				'bundle'             => 'waiver',
				'uid'                => $this->uinfo['uid'],
				'field_media_file_1' => [
					'target_id' => $fid,
					],
			]);

			// Calulate a name for the Media Entity
			$waiver_name  = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($this->uinfo['name']));
    	$waiver_name .= '-' . $this->uinfo['mid'] . '-signed-waiver'; 

			// Set the name and ave the media entity
			$media->setName($waiver_name)->setPublished(TRUE)->save();

			// Now it's time to create the registration entity
			$registration =  \Drupal::entityTypeManager()->getStorage('rusa_perm_registration')->create(
				[
					'uid'					=> $this->uinfo['uid'],
					'status'			=> 1,
					'field_rusa_' => $this->uinfo['mid'],
					'field_signed_waiver' => [
						'target_id' => $media->id(),
					],
  			],
			)->save();


      $this->messenger->addMessage(t("Your changes have been saved."), $this->messenger::TYPE_STATUS);
    }
    
    // It is possible that payment is made but the waiver is not valid

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
