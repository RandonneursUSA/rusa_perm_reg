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
        
        // Get the perm #
        $pid = $this->reg->get('field_perm_number')->getValue()[0]['value'];

        // Get the perm info
        $pobj = new RusaPermanents(['key' => 'pid', 'val' => $pid]);
        $perm = $pobj->getPermanent($pid);

        // Get the perm info we want to show in a table
        $rows = [
            ['Ride date',      $this->reg->get('field_date_of_ride')->getValue()[0]['value']],
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
        $form['pid'] = ['#type' => 'hidden', '#value' => $pid];
        $form['mid'] = ['#type' => 'hidden', '#value' => $this->uinfo['mid']];

        // Display some radio buttons
        $form['radio'] = [
            '#type'     => 'radios',
            '#options'  => [
                'dns' => $this->t('Did not start'),
                'dnf' => $this->t('Did not finish,  or finished out of time'),
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
                '#attributes' => ['onclick' => 'if(!confirm("Do you really want to cancel?")){return false;}'],
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

        // If radio = completed and time is empty
        if ($form_state->getValue('radio') == 'fin' && $form_state->getValue('hours') < 2) {
            $form_state->setErrorByName('hours', $this->t('If you completed the ride you must supply your time'));
         }   

        // Will need to validate time within limit here

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
            $form_state->setRedirect('user.page');
        }
        else {
            $results = [
                'status'  => $form_state->getValue('radio'),
                'hours'   => $form_state->getValue('hours'),
                'minutes' => $form_state->getValue('minutes'),
                'pid'     => $form_state->getValue('pid'),
                'mid'     => $form_state->getValue('mid'),
            ];

            $resobj = new RusaPermResults($results);
            $response = $resobj->post();
            
            if(isset($response->rsid)) {
                $this->save_reg_data($response->rsid);
                $this->messenger()->addStatus($this->t('Your results have been saved', []));
            }
            else {
                // Respond to error
                 $this->messenger()->addStatus($this->t('Your results have not been saved', []));
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


} // End of class  
