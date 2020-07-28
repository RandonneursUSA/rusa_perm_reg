<?php

namespace Drupal\rusa_perm_reg\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\smartwaiver\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
/**
 * Rusa Perm Controller
 *
 */
class RusaPermController  extends ControllerBase {

    protected $currentUser;
    protected $request;
    protected $entityTypeManager;
    protected $smartwaiverClient;
     
     
  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $currentUser, 
                              RequestStack $request, 
                              EntityTypeManagerInterface $entityTypeManager,
                              ClientInterface $smartwaiverClient) {
                              
      $this->currentUser       = $currentUser;
      $this->request           = $request;
      $this->entityTypeManager = $entityTypeManager;
      $this->smartwaiverClient = $smartwaiverClient;
  } 

	/**
     * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
          $container->get('current_user'),
          $container->get('request_stack'),
          $container->get('entity_type.manager'),
          $container->get('smartwaiver.client'),
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
        
        return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]); 
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

		// The waiver may not be ready yet
	    sleep(5);	
       	$waiver = $this->smartwaiverClient->waiver($wid);
       	
		// Now we have the waiver
        $tags      = $waiver->tags[0];
        $fields    = $waiver->participants[0]['customParticipantFields'];

        [$mid, $pid] = explode(' ', $tags);

        /*
        "10909"	"RUSA #"
        "1095"	"Permanent Route #"
        "2020-07-10"	"Ride Date YYYY-MM-DD"
        "Jul 10, 2020"	"Ride Date"
         */      

        // Get the custom field data
        foreach ($fields as $field) {
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


        // Convert the date
        // $date = strtotime($cfields['Date you want to ride']);
        // $date = date("Y-m-d", $date);

		// Save the registration
 		$reg = \Drupal::entityTypeManager()->getStorage('rusa_perm_reg_ride')->create(
            [
                'field_date_of_ride'    => $date,
                'field_perm_number'     => $pid,
                'field_waiver_id'       => $wid, 
                'field_rusa_member_id'  => $mid,
            ]);
        $reg->save();

		// Return to user profile Permanents tab
        return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]);
    }
	
	/* Waivers.
     *
     * Display a list of all waivers
     *
     */
    public function waivers() {
        $waivers = $this->smartwaiverClient->waivers([]);

        foreach ($waivers['waivers'] as $waiver) {
            $wids[] = $waiver['waiverId'];
        }

        // oop thought waiver ids and load each waiver
        foreach ($wids as $wid) {
            $waiver = $this->smartwaiverClient->waiver($wid);
            //dpm($waiver);
            $fields = $waiver->customWaiverFields;
            $cfields = [];
            foreach ($fields as $field) {
                $cfields[$field['displayText']] = $field['value'];
            }

            // Get the signature which is a base64 encoded PNG
            $img = $this->smartwaiverClient->get_signature($wid)->participantSignatures[0];

            // Build the table rows
            $rows[] = [
                $waiver->createdOn,
                $waiver->firstName,
                $waiver->lastName,
                $waiver->email,
                $waiver->tags[0],
                $cfields['Perm #'],
                $cfields['Date you want to ride'],
                $this->t("<img src='" . $img . "' alt='signature' style='max-height: 75px;' />"),
            ];
        }

        $header = ['Created on', 'First name', 'Last name', 'Email', 'RUSA #', 'Perm #','Ride date', 'Signature'];
        $output = [
            '#theme'    => 'table',
            '#header'   => $header,
            '#rows'     => $rows,
        ];

        return($output);

    }

} //EoC
