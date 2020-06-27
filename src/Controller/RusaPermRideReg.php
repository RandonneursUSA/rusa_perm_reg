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
 * RusaPermReg Controller
 *
 */
class RusaPermRideReg extends ControllerBase {

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
	  * Catch incoming result submission
	  */
	 public function resultSubmit() {

        // Data was passed in a Post
        $request = $this->request->getCurrentRequest();
        $results = $request->request->all();
        $regid   = $results['regid'];
        $rsid    = $results['rsid'];

        // Load the registration entity which was passed back to us in $regid
        $storage = $this->entityTypeManager->getStorage('rusa_perm_reg_ride');
        $reg     = $storage->load($regid); 

        // Set the result status id to the rsid that was passed to us
        $reg->set('field_rsid', $rsid);
        $reg->set('status', 0);
        $reg->save();

        // That's it we're done here.

        return $this->redirect('rusa_perm.reg',['user' => $this->currentUser->id()]); 
	}


} //EoC
