<?php

/**
 * @file
 *  ResultSubmit.php
 *
 * @Created 
 *  2020-06-28 - Paul Lieberman
 *
 * RUSA Perms Result Submission
 *
 * Gets data from a ride registration
 * Presents a form to get the finish time
 * Passes data off to the RusaPermResults Class for posting to backend
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rusa_perm_reg\RusaRideRegData;
use Drupal\rusa_api\RusaPermanents;
use Drupal\rusa_api\RusaPermResults;

/**
 * RusaMemberEditForm
 *
 * This is the Drupal Form class.
 * All of the form handling is within this class.
 *
 */
class ResultSubmit extends FormBase {

    protected $currentUser;
    protected $rideRegStorage;
    protected $uinfo;
    protected $reg;
    protected $time;

    /**
     * @getFormID
     *
     * Required
     *
     */
    public function getFormId() {
        return 'result_submit_form';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(AccountProxy $current_user, EntityStorageInterface $rideRegStorage) {
        $this->currentUser = $current_user;
        $this->rideRegStorage = $rideRegStorage;
        $this->uinfo = $this->get_user_info();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        $entity_type_manager = $container->get('entity_type.manager');
        return new static(
            $container->get('current_user'),
            $entity_type_manager->getStorage('rusa_perm_reg_ride'),
        );
    }

    /**
     * @buildForm
     *
     *
     */
    public function buildForm(array $form, FormStateInterface $form_state, $regid = NULL) {  

        // Load the registration entity
        $this->reg = $this->rideRegStorage->load($regid);
        
        // Check that results have not already been submitted
        // This should never happen but ...
        if (! $this->reg->get('field_rsid')->isEmpty()) {
            $this->messenger()->addError($this->t('It appeard results have alreay been submitted for this ride. Please check your results.'));
            $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);
        }
        
        // Get the perm #
        $pid = $this->reg->get('field_perm_number')->getValue()[0]['value'];
        $ride_date = $this->reg->get('field_date_of_ride')->getValue()[0]['value'];

        // Get the perm info
        $pobj = new RusaPermanents(['key' => 'pid', 'val' => $pid]);
        $perm = $pobj->getPermanent($pid);
        
        // Save the time calculation
        $this->time = $this->calculate_time($perm->dist);


        // Get the perm info we want to show in a table
        $rows = [
            ['Ride date',      $ride_date],
            ['Route #',        $pid],
            ['Route name',     $perm->name],
            ['Distance (km)',  $perm->dist],
            ['RUSA #',         $this->uinfo['mid']],
            ['Name',           $this->uinfo['name']],
        ];


        // Start the form
        $form[] = [
            '#title'  => $this->t('Submit results'),
        ];

        // Display the perm registration info
        $form['reginfo'] = [
            '#type'       => 'table',
            '#rows'       => $rows,
            '#attributes' => ['class' => 'rusa-table'],
        ];


        // Pass some data in hidden fields
        $form['pid']   = ['#type' => 'hidden', '#value' => $pid];
        $form['date']  = ['#type' => 'hidden', '#value' => $ride_date];
        
        
        // Display some radio buttons
        
        $form['radio'] = [
            '#type'     => 'radios',
            '#options'  => [
                'dns' => $this->t('Did not start'),
                'dnf' => $this->t('Did not finish,  or finished in more than %time.', 
                    ['%time' => $this->hours_and_minutes($this->time)]),
                'fin' => $this->t('Completed the ride in'),
             ],
             '#default_value' => 'fin',
        ];
        

        // Display time fields
        $form['hours'] = [
            '#type'  => 'textfield',
            '#title' => $this->t('Hours'),
            '#size'  => 20,
        ];

        $form['minutes'] = [
            '#type'  => 'textfield',
            '#title' => $this->t('Minutes'),
            '#size'  => 20,
        ];

        // Action buttons
        $form['actions'] = [
            '#type'   => 'actions',
            'cancel'  => [
                '#type'  => 'submit',
                '#value' => 'Cancel',
                '#attributes' => ['onclick' => 'if(!confirm("Click OK to cancel this result submission, or Cancel to return to result submission.")){return false;}'],
            ],
            'submit' => [
                '#type'  => 'submit',
                '#value' => 'Submit Results',
            ],
        ];


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
        $action = $form_state->getTriggeringElement();
        if ($action['#value'] == "Cancel") {
            $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);
        }

        elseif ($form_state->getValue('radio') == 'fin') {
            // If radio = completed and time is empty
            if ($form_state->getValue('hours') < 2) {
                $form_state->setErrorByName('hours', $this->t('If you completed the ride you must supply your time'));
            }           
            else {
                // Validate time within limit here
                $time = ($form_state->getValue('hours') * 60 ) + $form_state->getValue('minutes');
                if ($time > $this->time) {
                    // Time has exceeded limit
                    
                    // This is the only way I found to set these values
                    $input = $form_state->getUserInput();
                    $input['radio'] = 'dnf';
                    $input['hours'] = '';
                    $input['minutes'] = '';
                    $form_state->setUserInput($input);
                    
                    $this->messenger()->addError($this->t('Your finish time of %fintime exceeds the maximum allowed time of %time.', 
                        ['%fintime' => $this->hours_and_minutes($time),'%time' => $this->hours_and_minutes($this->time)]));
                    
                    // Go back to the form
                    $form_state->setRebuild();
                    
                }     
            }
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
    
        if ($action['#value'] == "Cancel") {
            $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);
        }

        elseif ($form_state->getValue('radio') === 'dns') {
            // Just cancel the registration
            $this->save_reg_data('dns');
            $this->messenger()->addStatus($this->t('Your results have been saved', []));
            $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);    
        }
        
        else {            
            $results = [
                'rider1-id'         => $this->uinfo['mid'],
                'rider1-firstName'  => $this->uinfo['fname'],
                'rider1-lastName'   => $this->uinfo['lname'],
                'rider1-hours'      => $form_state->getValue('hours'),            
                'rider1-minutes'    => $form_state->getValue('minutes'),
                'permid'            => $form_state->getValue('pid'),
                'date'              => $form_state->getValue('date'),
                'riderCount'        => 1,
                'accept'            => 1,
                'drupal'            => 1,
                'rider1-dnf'        => $form_state->getValue('radio') === 'dnf' ? 'true' : 'false',
                'drupal-regid'		=> $this->reg->id,
            ];
            
            // Log that we are submitting this result
            $this->getLogger('rusa_perm_reg')->notice("Results submitted for Ride Reg ID %regid for RUSA # %mid",
                    ['%mid' => $this->uinfo['mid'], '%regid' => $this->reg->id()]);
                        
            // Post results to the perm backend             
            $resobj = new RusaPermResults($results);
            $response = $resobj->post();      
            
            if (isset($response->rsid)) {            
                // Check that results have not already been submitted
                // This should never happen but ...
                if (! $this->reg->get('field_rsid')->isEmpty()) {
                    $this->messenger()->addError($this->t('It appears results have alreay been submitted for this ride. Please check your results.'));
                    $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);
                }
                else {
                    $this->save_reg_data($response->rsid);
                    $this->messenger()->addStatus($this->t('Your results have been saved', []));
                    
                    // Log after submitting this result
        			$this->getLogger('rusa_perm_reg')->notice("Registration entity @reg updated with result id @res ",
        					['@reg' => $this->reg->id(), '@res' => $response->rsid]);                    
                }
            }
            elseif (isset($response->errors)) {
                // Display error messages
                foreach ($response->errors as $error) {
                    $message .= '<br />' . $error;
                }                
                $this->messenger()->addError($this->t("Result submit returned the following errors: %message", 
                		['%message' => $message] ));
                $this->getLogger('rusa_perm_reg')->error("Result submit returned the following errors: %message",
                        ['%message' => $message]);
            }

            // Send the user back to main perm page
            $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);
		}
            
    }

    /**
     * Mark registration as complete
     *
     */
     protected function save_reg_data($rsid){
        // Set the result status id to the rsid that was passed to us
        $this->reg->set('field_rsid', $rsid);
        $this->reg->set('status', 0);
        $this->reg->save();                
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
     * Calculate time cutoff
     *
     * Return time in minutes
     */
    protected function calculate_time($dist) {
        return floor(60 * ($dist / 15.0));    
    }

    /**
     *
     * return time as hours and minutes
     *
     */
     protected function hours_and_minutes($time) {
        $hours = floor($time/60);
        $minutes = $time%60;
        return $hours . ' hours and ' . $minutes . ' minutes';
    }
     
    
} // End of class  
