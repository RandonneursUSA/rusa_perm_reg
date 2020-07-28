<?php

/**
 * @file
 *  RusaPermRegForm.php
 *
 * @Created 
 *  2020-06-05 - Paul Lieberman
 *
 * RUSA Permanents Registration
 *
 * Provides registration for the Perm Program
 * as well as ride registration.
 * 
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
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
    protected $ride_settings;
    protected $currentUser;
    protected $entityTypeManager;
    protected $uinfo;
    protected $regdata;
    protected $rideregdata;
    protected $regstatus;
    protected $step = 'start';
    protected $pid;

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
        $this->settings['prog'] = \Drupal::config('rusa_perm_reg.settings')->getRawData();
        $this->settings['ride'] = \Drupal::config('rusa_perm_ride.settings')->getRawData();
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
        $good_to_go = $reg_exists = $payment  = FALSE;

        // Determine the status of this registration
        if ($this->regdata->reg_exists()) {
            $reg_exists = TRUE;

            // Reg exists check payment
            if ($this->regdata->payment_received()) {
                $payment = TRUE;
                /*
                $form['payment'] = [
                   '#type'   => 'item',
                   '#markup' => $this->t($this->settings['prog']['yes_payment']),
                ];
                */
            }
            else {
                $form['payment'] = [
                   '#type'   => 'item',
                   '#markup' => $this->t($this->settings['prog']['no_payment']),
                ];

                // Display a link to the payment page
                $pay_link = $this->get_pay_link();

                $form['paylink'] = [
                    '#type'     => 'item',
                    '#markup'   => $pay_link,
                ];
            }            
        }
        else {
            // New Program Registration
            // Just display a submit button to create a new program registration
            $form['newreg'] = [
                '#type'   => 'item',
                '#markup' => $this->t('Before you can ride any permanents you must register for the program'),
            ];

            $this->step = 'progreg';
             
            $form['actions'] = [               
                'submit' => [
                    '#type'  => 'submit',
                    '#value' => 'Register for the  Perm Program',
                ],
            ]; 
		}
		
		/*
		 * Ride registration starts here
		 */
        
        // If all good then ready to ride
        if ( $reg_exists && $payment ){
            $form['ride'] = [
                '#type' 	=> 'item',
                '#markup'   => $this->t($this->settings['prog']['good_to_go']),
            ];

            // Show existing perm ride registrations
            if ($ridedata = $this->rideregdata->get_registrations() ) {
                $form['rideregtop'] = [
                    '#type'   => 'item', 
                    '#markup' => $this->t('<h3>Your current perm registrations.</h3>')
                ];
                
     			$form['ridereg'] = $this->get_current_registrations($ridedata);       
            }
 
 
            // Register for perm ride             
            $form['rideperm'] = [
                '#type'     => 'item',
                '#markup'   => $this->t('<h3>Register to ride a permanent.</h3>'),
                            
            ]; 
 
             $form['rideinstruct'] = [
                '#type'     => 'item',
                '#markup'   => $this->t($this->settings['ride']['instructions']),
            ];
 
            $form['pid'] = [
                '#type'         => 'textfield',
                '#title'        => $this->t('Route #'),                
                '#size'         => 6,
            ];

             $form['actions'] = [
                'submit' => [
                    '#type'  => 'submit',
                    '#value' => 'Find a route and register to ride',
                ],
            ];
            
            $this->step = 'ridereg';           
        }
        
        // Save reg status
        $this->regstatus = [
            'reg_exists'     => $reg_exists,
            'payment'        => $payment,
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
            
        if ($this->step === 'ridereg') {          
            // Check route validity
            $pid = trim($form_state->getValue('pid'));
            if (!empty($pid)) {       
                $route_valid = $this->is_route_valid($pid);
       
                if ($route_valid == 'sr') {            
                    // Compute the error message for SR-600                
                    $link = $this->get_sr_link();             
                    $msg = $this->settings['ride']['sr'];   
                    $msg = str_replace('[perm:id]', $pid, $msg);
                    $msg = str_replace('[perm:link]', $link, $msg);
                                
                    $form_state->setErrorByName('pid', $this->t($msg));
                }
                elseif ($route_valid == 'inactive') {
                    $form_state->setErrorByName('pid', $this->t('Permanent route %pid is not active.', ['%pid' => $pid]));
                }
                elseif ($route_valid == 'invalid') {
                    $form_state->setErrorByName('pid', $this->t('%pid is not a valid permanent route number.', ['%pid' => $pid]));
                }       
                elseif ($route_valid == 'valid') {
                    $this->pid = $pid;
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
    
        // For ridereg we redirect to select form
        if ($this->step === 'ridereg') {
            $pid = $form_state->getValue('pid');
            if (empty($pid)) {            
                $form_state->setRedirect('rusa_perm.select');
            }
            else {
                $form_state->setRedirect('rusa_perm.select', ['pid' => $pid]);
            }
        }
        elseif ($this->step === 'progreg') {
            
            // Check for existing program registration
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
                        'field_registration_year' => [
                            'value'     => date("Y-m-d"),
                            'end_value' => date("Y") . '-12-31',
                        ],                        
                    ]);               
                $reg->save();

                $this->messenger()->addStatus($this->t('Your perm program registration has been saved.', []));
                $this->logger('rusa_perm_reg')->notice('New perm program registration.', []);

                $form_state->setRedirect('rusa_perm.reg',['user' => $this->uinfo['uid']]);
            }
        }
        
    }

    /* Private Functions */ 

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
	 * Get a table of current registrations
     *
     */
     protected function get_current_registrations($ridedata) {
        $rows = [];
		
		// Step through each registration
		foreach ($ridedata as $id => $reg) {
			$row = [];
	        $links = [];

			// Add the data
			foreach($reg as $key => $val) {
				$row[] = $val;
			}
            
            // If ridedate is future we show cancel
            if ($reg['ride_date'] > date('Y-m-d')) {
            
                $url = Url::fromRoute('rusa_perm.cancel', ['regid' => $id]);
                $url->setOption('attributes', ['onclick' => 'if(!confirm("Do you really want to cancel this ride registration?")){return false;}']);
                $links['cancel'] = [                
                    'title' => $this->t('Cancel registration'),
                    'url'   =>  $url,                    
                ];
            }
            else {
                $url = 
			    $links['results'] = [
				    'title' => $this->t('Submit results'),
				    'url'  => Url::fromRoute('rusa_perm.submit', ['regid' => $id]),
			    ];
            }

			// Add operations links
			$row[] = [ 
				'data' => [
					'#type' => 'operations', 
					'#links' => $links,
			   ],
			];

			$rows[] = $row;
		};
   
		return [
			'#type'    => 'table',
			'#header'   => ['Route #', 'Ride Date', 'Name', 'Km', 'Climb (ft.)', 'Description', 'Operations'],
			'#rows'     => $rows,
			'#responsive' => TRUE,
			'#attributes' => ['class' => ['rusa-table']],
		];
	}
	
	
	/**
	 * Get a table of current registrations
     *
     */
     protected function get_perm($pid) {
        
		$perm = $this->rideregdata->getPerm($pid);
		$row = [
            'pid'       => $pid,
            'pname'     => $perm->name,
            'pdist'     => $perm->dist, 
            'pclimb'    => $perm->climbing, 
            'pdesc'     => $perm->description,
        ];
		
		return [
			'#type'    => 'table',
			'#header'   => ['Route #', 'Name', 'Km', 'Climb (ft.)', 'Description' ],
			'#rows'     => [$row],
			'#responsive' => TRUE,
			'#attributes' => ['class' => ['rusa-table']],
		];
	}
	
	
    /**
     * Build a link to the payment page
     *
     */
    protected function get_pay_link() {
        $regid = $this->regdata->get_reg_id();
        $url = Url::fromRoute('rusa_perm.pay');
        $url->setOption('query',  ['mid' => $this->uinfo['mid'], 'regid' => $regid]);
        return Link::fromTextAndUrl('Proceed to the payment page', $url)->toString();
    }
    
    /**
     * Check if route is valid
     *
     */
    protected function is_route_valid($pid) {
        $perms = new RusaPermanents(['key' => 'pid', 'val' => $pid]);
        if (! $perms->getPermanents()){
            return 'invalid';
        }
        if ($perms->isInactive($pid)) {
            return 'inactive';
        }
        if ($perms->isSr($pid) == '1') {
            return 'sr';
        }
        return 'valid';
      
    }
 
    /**
     *
     * Build a link to the SR-600 page
     *
     */
    protected function get_sr_link() {
        $url = Url::fromRoute('rusa_perm.sr');
        return Link::fromTextAndUrl('SR-600 page', $url)->toString();
    }
 
} // End of class  
