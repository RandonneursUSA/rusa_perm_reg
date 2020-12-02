<?php

namespace Drupal\rusa_perm_reg\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Smartwaiver\Smartwaiver;
use Smartwaiver\Exceptions\SmartwaiverHTTPException;
use Drupal\rusa_perm_reg\RusaPermReg;

/**
 * Rusa Perm Controller
 *
 */
class RusaPermController  extends ControllerBase {

    protected $currentUser;
    protected $request;
    protected $entityTypeManager;
    protected $smartwaiverClient;
    protected $keyRepository;
    protected $permReg;
     
     
  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $currentUser, 
                              RequestStack $request, 
                              EntityTypeManagerInterface $entityTypeManager,
                              KeyRepositoryInterface $key_repository) {
                              
      $this->currentUser       = $currentUser;
      $this->request           = $request;
      $this->entityTypeManager = $entityTypeManager;
      $this->keyRepository     = $key_repository;
      
      $this->permReg = new RusaPermReg();
      $this->permReg->query($currentUser->id());

      $api_key = \Drupal::config('rusa_perm_ride.settings')->get('api_key');      
      $this->smartwaiverClient = new Smartwaiver($this->apiKey($api_key));
  } 

	/**
     * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
          $container->get('current_user'),
          $container->get('request_stack'),
          $container->get('entity_type.manager'), 
          $container->get('key.repository'),
		);
	 }

     /**
	  * Catch incoming payment received
	  */
	 public function payReceived() {

        // Data was passed in a Post
        $request = $this->request->getCurrentRequest();
        $results = $request->request->all();
                
        $this->getLogger('rusa_perm_reg')->notice('Perm program payment post received');
        
        // This is the data we should receive
        $regid  = $results['regid'];
        // $mid    = $results['mid']; We don't actually need this
        
        if (empty($regid)) {
            // Log an error message
            $this->getLogger('rusa_perm_reg')->error('Perm program payment missing parameter regid %regid',['%regid' => $regid]);
        }           
        else {
            // Load the registration entity which was passed back to us in $regid
            $storage = $this->entityTypeManager->getStorage('rusa_perm_registration');
            $reg     = $storage->load($regid); 

            // Set the payment received boolean and the date
            $reg->set('field_payment_received', TRUE);
            $reg->set('field_date_payment_received', date('Y-m-d', time()));
            $reg->save();
        }
        return ['#markup' => 'Payment posted to perm program registration # ' . $regid];
        //return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]); 
	}
	
	/**
	 * Cancel a perm registration
	 *
	 */
	public function permCancel($regid) {
	    // Load the registration entity which was passed back to us in $regid
        $storage = $this->entityTypeManager->getStorage('rusa_perm_reg_ride');
        $reg     = $storage->load($regid); 

        // Just set the status to zero for now - Maybe delete later
	    $reg->set('status', 0);
	    $reg->save();
	
	    return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]); 
    	
	}
	
	
	/**
     * Incoming waiver
     *
     * Rider has been redirected here after signing a waiver
     * The waiver ID is in the query string
     * We want to retrieve the waiver and then redirect rider to their profile permanents tab
     *
     */
    public function incoming() {

        $request   = \Drupal::request();
        $query     = $request->query; 
    	$wid       = $query->get('waiverid');    	

        // Get the waiver   	
        $attempts = 1;
        do {
            try {
       	        $waiver = $this->smartwaiverClient->getWaiver($wid);
            }
            catch(SmartwaiverHTTPException $se) {
                $attempts++;
                sleep(2);
                continue;
        
            }
            break;
        } while($attempts < 5);
        
        if ($attempts == 5) {
            $this->getLogger('rusa_perm_reg')->error('Could not retreive waiver %waiver.', ['%waiver' => $wid]);
            $this->messenger()->addError('Could not retrieve waiver, please try registering again.');
        }
        
        else {
            $this->getLogger('rusa_perm_reg')->notice('Waiver retrieved after %count attempts.', ['%count' => $attempts]);
        
            // Now we have the waiver
            $tags      = $waiver->tags[0];
            $fields    = $waiver->participants[0]->getArrayInput();
            $pfields   = $fields['customParticipantFields'];

            [$mid, $pid] = explode(' ', $tags);

            // Get the custom field data
            foreach ($pfields as $field) {
                $cfields[$field['displayText']] = $field['value'];
            }
            $wmid = trim($cfields['RUSA #']);
            $wpid = trim($cfields['Permanent Route #']);
            $date = $cfields['Ride Date YYYY-MM-DD'];
     
            // Make sure mid matches what we passed in the tags
            if ($mid != $wmid) {
                $this->messenger()->addWarning($this->t('RUSA # entered in waiver is not the same as the rider who submitted the form.'));
                return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]);
            }

            // Make sure pid matches what we passed in the tags
            if ($pid != $wpid) {
                $this->messenger()->addWarning($this->t('Route # entered in waiver is not the same as what was entered in the form.'));
                return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]);
            }


            // Check if valid program registration for date of ride       
            $good_to_save = FALSE; 
            $year   = date("Y", strtotime($date));
            $status = $this->permReg->getRegStatus($year);            
            if ($status['reg_exists'] && $status['payment']) {            
                $good_to_save = TRUE;            
            }
            
            // In December next year's registration is also valid
            elseif (date('m') > 11) {	
                $next_year = $year + 1 ;
                $status = $this->permReg->getRegStatus($next_year);  
                if ($status['reg_exists'] && $status['payment']) {            
                    $good_to_save = TRUE;            
                }
            }
                       
            if ($good_to_save) {            

                // Save the registration
                $reg = \Drupal::entityTypeManager()->getStorage('rusa_perm_reg_ride')->create(
                    [
                        'field_date_of_ride'    => $date,
                        'field_perm_number'     => $pid,
                        'field_waiver_id'       => $wid, 
                        'field_rusa_member_id'  => $mid,
                    ]);
                $reg->save();
            }
            else {
                $this->messenger()->addWarning($this->t('You are not registered to ride perms in %year', ['%year' => $year]));
                return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]);
            }
        }
        
		// Return to user profile Permanents tab
        return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]);
    }
	
    
    /**
     * @param string $name
     *
     * @return string
     */
    protected function apiKey($key_id) {
        $key = $this->keyRepository->getKey($key_id);
        $value = trim($key->getKeyValue());
        return $value;
    }
    
    
} //EoC
