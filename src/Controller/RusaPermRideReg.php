<?php

namespace Drupal\rusa_perm_reg\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
/**
 * RusaPermReg Controller
 *
 */
class RusaPermRideReg extends ControllerBase {

    protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request) {
      $this->request = $request;
  } 

	/**
     * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
          $container->get('request_stack')
		);
	 }

	 /**
	  * Catch incoming result submission
	  */
	 public function resultSubmit() {

        // Data was passed in a Post
        $request  = $this->request->getCurrentRequest();
        dpm($request->request->all());

        $output = ['#markup' => $this->t('All good here')];

        return $output;
	}


} //EoC
