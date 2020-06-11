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
use Drupal\media\Entity\Media;
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
        $this->settings = \Drupal::config('rusa_perm_reg.settings')->getRawData();
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
        $form[] = [
            '#title'  => $this->t('Program Registration'),
        ];

        // Display member name and # so they know who they are
        $form['user'] = [
            '#type'		=> 'item',
            '#markup' => $this->t($this->uinfo['name'] . ' RUSA #' . $this->uinfo['mid']),
        ];

        // Set all status to FALSE
        $good_to_go = $reg_exists = $waiver_exists = $waiver_expired = $payment = $approved = FALSE;

        // Determine the status of this regustration
        if ($this->regdata->reg_exists()) {
            $reg_exists = TRUE;

            // Reg exists check waiver
            if ( $this->regdata->waiver_exists()){
                $waiver_exists = TRUE;

                // Waiver exists check expired
                if ($this->regdata->waiver_expired()) {
                    $waiver_expired = TRUE;
                }
            }

            // Reg exists check payment
            if ($this->regdata->payment_received()) {
                $payment = TRUE;
            }

            // Reg exists check approval
            if ($this->regdata->registration_approved()) {
                $approved = TRUE;
            }
        }

        // If all good then ready to ride
        if ($waiver_exists && !$waiver_expired && $payment && $approved){ 
            $approver = $this->regdata->registration_approved();

            // Get registration id
            $regid = $this->regdata->get_reg_id();

            // Get registration dates
            $regdates = $this->regdata->get_reg_dates();

            $approver = $this->regdata->registration_approved();
            // Registration is complete so show it
 
            // Build a link to view the registration
            $reg_link = Link::fromTextAndUrl('Current perm program registration', Url::fromUri('internal:/rusa_perm_registration/' . $regid))->toString();
            $active_reg = $regdates[0] . ' to ' . $regdates[1];

            $form['curreg'] = [
                '#type'   => 'item',
                '#markup' => $this->t('Your ' . $reg_link . ' is valid from ' . $active_reg . ' Approved by: ' . $approver ),
            ];

            $form['ride'] = [
                '#type' 	=> 'item',
                '#markup' => $this->t($this->settings['good_to_go']),
            ];
        }
        else {
            //Now display some status messages
            if ($waiver_exists && !$waiver_expired) {        
                $form['waiver_good'] = [
                        '#type'   => 'item',
                        '#markup' => $this->t($this->settings['good_release']),
                    ];
            }
            if ($waiver_exists && $waiver_expired) {        
                $form['waiver_exp'] = [
                    '#type'   => 'item',
                    '#markup' => $this->t($this->settings['expired']),
                ];
            }
            if ($reg_exists && !$waiver_exists) {        
                $form['no_waiver'] = [
                    '#type'   => 'item',
                    '#markup' => $this->t($this->settings['no_release']),
                ];
            }
            
            if ($reg_exists &&  $payment) {        
                $form['payment'] = [
                   '#type'   => 'item',
                   '#markup' => $this->t($this->settings['yes_payment']),
                ];
            }
            elseif ($reg_exists && !$payment ) {
                $form['payment'] = [
                   '#type'   => 'item',
                   '#markup' => $this->t($this->settings['no_payment']),
                ];
            }

            if ($waiver_exists && !$waiver_expired && $payment && !$approved){ 
                // Just waiting approval
                $form['approval'] = [
                    '#type'   => 'item',
                    '#markup' => $this->t($this->settings['no_approval']),
                ];
            }
        }
        // End of status messages

        // Start new registration
        if (!$reg_exists || !$waiver_exists || $waiver_expired) {
            
            // Get release form URI and build a link
            $this->release_form  = $this->regdata->get_release_form();
            $url = file_create_url($this->release_form['uri']);
            $release_link = Link::fromTextAndUrl('Download Release Form', Url::fromUri($url,['attributes' => ['target' => '_blank']]))->toString();

            // Process steps
            $steps = [
                $release_link,
                'Print, date, sign, and scan the release form.(maybe add a link for some tips on how to scan and prepare for upload)',
                'Upload your signed release form.', 
                'When you submit this form you will be redirected to our payment portal to pay your annual fee.',
            ];


            $form['instruct'] = [
                '#type'   => 'item',
                '#markup' => $this->t($this->settings['instructions']),
            ];

            $form['steps'] = [
                '#theme' => 'item_list',
                '#list_type' => 'ul',
                '#title' => 'Registration Steps',
                '#items' => $steps,
                '#attributes' => ['class' => ['rusa-list']],
            ];

            $form['waiver_upload'] = [
                 '#type'	=> 'managed_file',
                 '#title'	=> $this->t('Upload your signed release form.'),
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
       
            // Attach the Javascript and CSS, defined in rusa_api.libraries.yml.
            // $form['#attached']['library'][] = 'rusa_api/rusa_script';
            $form['#attached']['library'][] = 'rusa_api/rusa_style';
        }
        return $form;
    }

    /**
     * @validateForm
     *
     * Required
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if (! $form_state->getValue('waiver_upload')){
            $form_state->setError($form['waiver_upload'], $this->t('You have not uploaderd your signed waiver.'));
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
            // Get the fid of the uploaded waiver
            $fid = $form_state->getValue('waiver_upload')[0];
 

            // Rename the upload file


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
                    'uid'		  => $this->uinfo['uid'],
                    'status'      => 1,
                    'field_rusa_' => $this->uinfo['mid'],
                    'field_signed_waiver' => [
                        'target_id' => $media->id(),
                    ],
                ],
                )->save();


            $this->messenger()->addStatus($this->t('Your perm program registration has been saved', []));
            // $this->logger('rusa_perm_reg')->notice('Updated new rusa perm ride registration %label.', $logger_arguments);

           $form_state->setRedirect('user.page');
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
