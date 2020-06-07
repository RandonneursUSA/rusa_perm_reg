<?php

/**
 * @file
 *  RusaRegData.php
 *
 * @Created 
 *  2020-06-05 - Paul Lieberman
 *
 * Get Registration Data
 *
 * Returns status for various steps in the registration process
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class RusaRegData {

  protected $currentUser;
  protected $uid;
  protected $reg;
  protected $regid;
  protected $waiver;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->currentUser = \Drupal::currentUser();

    $this->uid = $this->currentUser->id();

    //Get registration data
 		$query = \Drupal::entityTypeManager()->getStorage('rusa_perm_registration');
    $query_result = $query->getQuery()
      ->condition('status', 1)
			->condition('uid', $this->uid) 
      ->execute();

		// Load the registration entity
    if ($query_result) {
   
      $id = array_shift($query_result);
		  $this->reg = $query->load($id);
      $this->regid = $id;
    }
    else {
      $this->reg = FALSE;
    }
  }

  /**
   * Get reg id
   *
   * Return regid
   */
   public function get_reg_id(){
     return $this->regid;
   }


  /**
   * reg_exists
   *
   * Return boolean
   */
  public function reg_exists() {
    return $this->reg == FALSE ? FALSE : TRUE;
  }

  /**
   * reg dates
   * 
   * return the registration dates
   */
  public function get_reg_dates() {
	 $reg_dates = $this->reg->get('field_registration_year')->getValue();
	 return [$reg_dates[0]['value'], $reg_dates[0]['end_value']];
 }  

 /**
  * Waiver exists
  *
  * Return boolean
  */
  public function waiver_exists() {
    // Check to see if the waiver has been uploaded
    $this->waiver = $this->reg->get('field_signed_waiver');
    return $this->waiver->isEmpty() ? FALSE : TRUE;
    
  }

  /**
   * waiver expired
   *
   * Return boolean
   */
  public function waiver_expired() { 
    //Check to see if the waiver is expired
    $waiver_file  = $this->waiver->referencedEntities();
    $expired_flag = $waiver_file[0]->get('field_expired')->getValue();
    return  $expired_flag[0]['value'] == 1 ? TRUE : FALSE;
  }

  /**
   * payment received
   *
   * return boolean
   */
   public function payment_received() {
    // Check to see if payment has been received
    $payment_flag = $this->reg->get('field_payment_received')->getValue();
    return $payment_flag[0]['value'] == 1 ? TRUE : FALSE;
  }

  /**
   * registration approved
   *
   * return FALSE or approver's name
   */
   public function registration_approved() {
     // Check to see if registration has been approved
     // And if so by who
     $approved_by =  $this->reg->get('field_approved_by');
     if ($approved_by->isEmpty()) {
       return FALSE;
     }
     else {
       return $approved_by->referencedEntities()[0]->get('field_display_name')->getValue()[0]['value'];
     }
  }


} // End of class  
