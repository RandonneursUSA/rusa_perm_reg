<?php

namespace Drupal\rusa_perm_reg\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $currentUser, RequestStack $request, EntityTypeManagerInterface $entityTypeManager ) {
      $this->currentUser       = $currentUser;
      $this->request           = $request;
      $this->entityTypeManager = $entityTypeManager;
  } 

	/**
     * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
          $container->get('current_user'),
          $container->get('request_stack'),
          $container->get('entity_type.manager'),
		);
	 }

     /**
	  * Catch incoming payment received
	  */
	 public function payReceived() {

        // Data was passed in a Post
        $request = $this->request->getCurrentRequest();
        $results = $request->request->all();
        $regid   = $results['regid'];
        $rsid    = $results['mid'];

        // Load the registration entity which was passed back to us in $regid
        $storage = $this->entityTypeManager->getStorage('rusa_perm_registration');
        $reg     = $storage->load($regid); 

        // Set the payment received boolean and the date
        $reg->set('field_payment_received', TRUE);
        $reg->set('field_date_payment_received', date('Y-m-d', time()));
        $reg->save();

        // That's it we're done here.

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

} //EoC
