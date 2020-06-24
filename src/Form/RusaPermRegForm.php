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
use Drupal\rusa_perm_reg\RusaRideRegData;
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
    protected $entityTypeManager;
    protected $uinfo;
    protected $regdata;
    protected $rideregdata;
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
        $this->entityTypeManager = $entityTypeManager;
        $this->uinfo = $this->get_user_info();
        $this->regdata = new RusaRegData();
        $this->rideregdata = new RusaRideRegData($this->uinfo['uid']);
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
        $good_to_go = $reg_exists = $payment = $approved = FALSE;

        // Determine the status of this registration
        if ($this->regdata->reg_exists()) {
            $reg_exists = TRUE;

            // Reg exists check payment
            if ($this->regdata->payment_received()) {
                $payment = TRUE;
            }

            // Reg exists check approval
            if ($this->regdata->registration_approved()) {
                $approved = TRUE;
            }
        }
        else {
            // New Registration
            $form['newreg'] = [
                '#type'   => 'item',
                '#markup' => $this->t('Before you can ride any permanents you must register for the program'),
            ];

            $form['actions'] = [
                '#type'   => 'actions',
                'cancel'  => [
                    '#type'  => 'submit',
                    '#value' => 'Cancel',
                    '#attributes' => ['onclick' => 'if(!confirm("Do you really want to cancel?")){return false;}'],
                ],
                'ride_submit' => [
                    '#type'  => 'submit',
                    '#value' => 'Register for the  Perm Program',
                ],
            ]; 
		}
        // If all good then ready to ride
        if ( $reg_exists && $payment && $approved){
            $form['ride'] = [
                '#type' 	=> 'item',
                '#markup'   => $this->t($this->settings['good_to_go']),
            ];

            // Show existing perm ride registrations
            if ($ridedata = $this->rideregdata->get_registrations() ) {
                $form['rideregtop'] = ['#type' => 'item', '#markup' => $this->t('<h3>Your current perm registrations.</h3>')];
                $form['ridereg'] = [
                    '#theme'    => 'table',
                    '#header'   => ['Route #', 'Ride Date', 'Name', 'Km', 'Climb (ft.)', 'Description'],
                    '#rows'     => array_values($ridedata),
                    '#responsive' => TRUE,
                    '#attributes' => ['class' => ['rusa-table']],
                ];
            }

            
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

            // Display a link to sign the waiver
            $waiver_link = $this->smartwaiver_link(); 


            $form['instruct2'] = [
                '#type'     => 'item',
                '#markup'   => $this->t('Once you have the route # of the perm you want to ride,  you can  ' .
                '<br />' . $waiver_link),
            ];

        }
        else {
            // Not ready to ride 
            // display some status messages
                    
           if ($reg_exists && !$payment ) {
                $form['payment'] = [
                   '#type'   => 'item',
                   '#markup' => $this->t($this->settings['no_payment']),
                ];

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
            elseif ($reg_exists &&  $payment) {        
                $form['payment'] = [
                   '#type'   => 'item',
                   '#markup' => $this->t($this->settings['yes_payment']),
                ];
            }

            if ( $reg_exists && $payment && !$approved){ 
                // Just waiting approval
                $form['approval'] = [
                    '#type'   => 'item',
                    '#markup' => $this->t($this->settings['no_approval']),
                ];
            }
        }
        // End of status messages

        // Save reg status
        $this->regstatus = [
            'reg_exists'     => $reg_exists,
            'payment'        => $payment,
            'approved'       => $approved];
    

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
        /*
        if ($stage == 'ridereg') {
            // Get the id that was entered
            $pid = $form_state->getValue('route_id');
            $permobj = new RusaPermanents(['key' => 'pid', 'val' => $pid]);
            $perm = $permobj->getPermanent($pid);
            $form_state->set('perm', $perm);

            $form_state->setRebuild();
        }
        */
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
        else {

		 if ($this->regstatus['reg_exists']) {
			// Registration exists so we are just going to relace the waiver
			$reg = $this->regdata->get_reg_entity();

		}
		else {
			// New registration
			// Create the registration entity
			$reg = \Drupal::entityTypeManager()->getStorage('rusa_perm_registration')->create(
				[
					'uid'		  => $this->uinfo['uid'],
					'status'      => 1,
					'field_rusa_' => $this->uinfo['mid'],
				]);
			$reg->save();
            $this->messenger()->addStatus($this->t('Your perm program registration has been saved.', []));
            $this->logger('rusa_perm_reg')->notice('New perm program registration.', []);

            $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);
		}
            
//            // Post to the Perl script for paypal payment
//            $results = ['mid' => $this->uinfo['mid'], 'permregid' => $reg->id()];
//
//            // Initialize our client to put the results.
//            $client = new RusaClient();
//            $err = $client->perm_pay($results);
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

        $uinfo['uid']   = $user_id;
        $uinfo['name']  = $user->get('field_display_name')->getValue()[0]['value'];
        $uinfo['fname'] = $user->get('field_first_name')->getValue()[0]['value'];
        $uinfo['lname'] = $user->get('field_last_name')->getValue()[0]['value'];
        $uinfo['dob']   = str_replace('-', '', $user->get('field_date_of_birth')->getValue()[0]['value']);
        $uinfo['mid']   = $user->get('field_rusa_member_id')->getValue()[0]['value'];
        return($uinfo);
    }

    /**
     * Generate Smartwaiver link
     *
     */
    protected function smartwaiver_link() { 
        $swurl = 'https://waiver.smartwaiver.com/w/5eea4cfb2b05a/web/';
        $swurl .= '?wautofill_firstname='   . $this->uinfo['fname'];
        $swurl .= '&wautofill_lastname='    . $this->uinfo['lname'];
        $swurl .= '&wautofill_dobyyyymmdd=' . $this->uinfo['dob'];
        $swurl .= '&wautofill_tag='         . $this->uinfo['mid'];
        
        return(Link::fromTextAndUrl('Sign the waiver', Url::fromUri($swurl))->toString());

    }

} // End of class  
