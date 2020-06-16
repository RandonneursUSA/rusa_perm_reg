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
use Drupal\rusa_api\RusaPermanents;
use Drupal\rusa_api\Client\RusaClient;

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
    // protected $messenger;
    protected $entityTypeManager;
    protected $uinfo;
    protected $regdata;
    protected $release_form;
    protected $regstatus;

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
       //  $this->messenger   = \Drupal::messenger();
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
        $good_to_go = $reg_exists = $waiver_exists = $waiver_expired = $waiver_invalid = $payment = $approved = FALSE;

        // Determine the status of this registration
        if ($this->regdata->reg_exists()) {
            $reg_exists = TRUE;

            // Reg exists check waiver
            if ( $this->regdata->waiver_exists()){
                $waiver_exists = TRUE;

                // Waiver exists check invalid
                if ($this->regdata->waiver_invalid()) {
                    $waiver_invalid = TRUE;
                }

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
        if ($waiver_exists && !$waiver_expired && !$waiver_invalid && $payment && $approved){ 
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
/*
            $form['curreg'] = [
                '#type'   => 'item',
                '#markup' => $this->t('Your ' . $reg_link . ' is valid from ' . $active_reg . ' Approved by: ' . $approver ),
            ];
*/
            $form['ride'] = [
                '#type' 	=> 'item',
                '#markup'   => $this->t($this->settings['good_to_go']),
            ];
       
            if ($perm = $form_state->get('perm')) {
                // Confirm this route
                $form['confirm']  = [
                    '#type'     => 'item',
                    '#markup'   => $this->t('Is this the perm route you want to register for?'),
                ];

                $form['confirm_perm'] = [
                    '#type'     => 'table',
                    '#header'   => ['Name', 'Km', 'Feet', 'Description'],
                    '#rows'     => [[$perm->name, $perm->dist, $perm->climbing, $perm->description]],
                    '#attributes' => ['class' => ['rusa-table']],
                ];

                $form_state->set('stage', 'ride_confirm');
            }
            else {
                // Display good to go message
                $form['ride'] = [
                    '#type' 	=> 'item',
                    '#markup'   => $this->t($this->settings['good_to_go'] . '  Active dates: ' .  $active_reg),
                ];
 
                // Display  a link to the route search page
                $search_link = Link::createFromRoute(
                        'Search for a permanent route to ride', 
                        'rusa_perm.search',
                        ['attributes' => ['target' => '_blank']],  
                    )->toString();

                $form['search'] = [
                    '#type'     => 'item',
                    '#markup'   => $this->t('To register for  a perm ride, first find the route you want to ride using our serch form.' . 
                    '<br />' . $search_link),
                ];


                $form['instruct2'] = [
                    '#type'     => 'item',
                    '#markup'   => $this->t('Once you have the perm route # of the route you want to ride you can proceed with this form.'),
                ];

                // Display a form to enter a perm # and date 
                $form['route_id'] = [
                    '#type'      => 'textfield',
                    '#title'     => $this->t('Perm route #'), 
                    '#size'      => 4,
                    '#maxlength' => 4,
                    '#required'  => 1,
                ];

                $form['ride_date'] = [
                    '#type'     => 'date',
                    '#title'    => $this->t('The date you plan on doing this ride'),
                    '#required' => 1,
                ];

                $form_state->set('stage', 'ridereg');
            }

            // Actions wrapper
            $form['actions'] = [
                '#type'   => 'actions',
                'cancel'  => [
                    '#type'  => 'submit',
                    '#value' => 'Cancel',
                    '#attributes' => ['onclick' => 'if(!confirm("Do you really want to cancel?")){return false;}'],
                ],
                'ride_submit' => [
                    '#type'  => 'submit',
                    '#value' => 'Register for Perm Ride',
                ],
            ];
 
        }
        else {
            // Not ready to ride 
            // display some status messages
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
             if ($waiver_exists && $waiver_invalid) {        
                $form['waiver_inv'] = [
                    '#type'   => 'item',
                    '#markup' => $this->t($this->settings['bad_release']),
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

        
            if ($waiver_exists && !$payment){ 

                // Display a link to the payment page

                $regid = $this->regdata->get_reg_id();
                $url = Url::fromRoute('rusa_perm.pay');
                $url->setOption('query',  ['mid' => $this->uinfo['mid'], 'regid' => $regid]);
                $pay_link = Link::fromTextAndUrl('Proceed to the payment page', $url)->toString();

                $form['paylink'] = [
                    '#type'     => 'item',
                    '#markup'   => $pay_link,
                ];

                $form_state->set('stage', 'regpay');
            }


            if ($waiver_exists && !$waiver_expired && !$waiver_invalid && $payment && !$approved){ 
                // Just waiting approval
                $form['approval'] = [
                    '#type'   => 'item',
                    '#markup' => $this->t($this->settings['no_approval']),
                ];
            }
        }
        // End of status messages

        // Show the waiver upload form
        if (!$reg_exists || !$waiver_exists || $waiver_expired || $waiver_invalid) {
            
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
                 '#upload_location'  => 'private://waivers/' . $this->uinfo['mid'] . '/',
                 '#multiple'         => FALSE,
                 '#description'      => t('Allowed extensions: pdf png jpg jpeg'),
                 '#upload_validators'    => [
                    'file_validate_extensions'    => array('pdf png jpg jpeg'),
                    'file_validate_size'          => array(25600000),
                  ],
                  '#required'   => 1,
            ];

            $form_state->set('stage', 'progreg');
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
                    '#value' => 'Register for Perm Program',
                ],
            ];
      

            // Save reg status
            $this->regstatus = [
                'reg_exists'     => $reg_exists,
                'waiver_exists'  => $waiver_exists,
                'waiver_expired' => $waiver_expired,
                'waiver_invalid' => $waiver_invalid,
                'payment'        => $payment,
                'approved'       => $approved];
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
        
        $stage = $form_state->get('stage');

        // Get info on the selected perm
        if ($stage == 'ridereg') {
            // Get the id that was entered
            $pid = $form_state->getValue('route_id');
            $permobj = new RusaPermanents(['key' => 'pid', 'val' => $pid]);
            $perm = $permobj->getPermanent($pid);
            $form_state->set('perm', $perm);

            $form_state->setRebuild();
        }
    } // End function validate


    /**
     * @submitForm
     *
     * Required
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $action = $form_state->getTriggeringElement();
        $stage = $form_state->get('stage');
        
        if ($action['#value'] == "Cancel") {
            $form_state->setRedirect('user.page');
        }
        elseif ($stage == 'progreg') {

            // Calulate a name for the Media Entity
            // User's name-signed-release-version
            $waiver_name  = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($this->uinfo['name']));
            $waiver_name .= '-' . $this->uinfo['mid'] . '-signed-waiver-' . $this->release_form['version']; 


            // Get the fid of the uploaded waiver
            $fid = $form_state->getValue('waiver_upload')[0];

            // Create a media entity
            $media = Media::create([
                    'bundle'             => 'waiver',
                    'uid'                => $this->uinfo['uid'],
                    'field_media_file_1' => [
                        'target_id' => $fid,
                    ],
                    'field_realease_form' => [
                        $this->release_form['id'],
                    ],
            ]);

            // Set the name and ave the media entity
            $media->setName($waiver_name)->setPublished(TRUE)->save();


            if ($this->regstatus['reg_exists']) {
                // Registration exists so we are just going to relace the waiver
                $reg = $this->regdata->get_reg_entity();

                // If the existing waiver is invalid we will delete if first
                if ($this->regstatus['waiver_invalid']) {
                    // Get the id of the exiting waiver
                    $waiver_id = $reg->get('field_signed_waiver')->getValue()[0]['target_id'];
                    $entity = \Drupal::entityTypeManager()->getStorage('media')->load($waiver_id);
                    $entity->delete();
                }

                // Update the registration entity
                $reg->set('field_signed_waiver',  [ 'target_id' => $media->id() ]);
                $reg->save();
            
            }
            else {
                // New registration
                // Create the registration entity
                $reg = \Drupal::entityTypeManager()->getStorage('rusa_perm_registration')->create(
                    [
                        'uid'		  => $this->uinfo['uid'],
                        'status'      => 1,
                        'field_rusa_' => $this->uinfo['mid'],
                        'field_signed_waiver' => [
                            'target_id' => $media->id(),
                        ],
                    ]);
                $reg->save();

            }
            
//            // Post to the Perl script for paypal payment
//            $results = ['mid' => $this->uinfo['mid'], 'permregid' => $reg->id()];
//
//            // Initialize our client to put the results.
//            $client = new RusaClient();
//            $err = $client->perm_pay($results);
//
//            $this->messenger()->addStatus($this->t('Your perm program registration has been saved.', []));
//            $this->logger('rusa_perm_reg')->notice('Updated perm program registration.', []);
//
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
