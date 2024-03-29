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
 * @todo
 *  - uid should be passed in to constructor √
 *  - eliminate date filter and get all active registration √
 *  - store registrations in an array keyed by year √
 *  - provide functions to get current and next year's registration
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg;


use Drupal\Core\Entity\Query;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
/**
 *
 */
class RusaRegData {

    protected $reg;
    protected $regid;

    /**
     * {@inheritdoc}
     */
    public function __construct($uid) {
       

        // Get registration data
		$storage = \Drupal::service('entity_type.manager')->getStorage('rusa_perm_registration');
		$query = $storage->getQuery()
            ->condition('status', 1)
            ->condition('uid', $uid)
            ->accessCheck(TRUE); 
        $query_result = $query->execute();

        // Store registrations keyed by year
        foreach ($query_result as $id) {
            $reg = $storage->load($id);
            $regid = $id;
            $reg_dates = $reg->get('field_registration_year')->getValue();
            $year = date('Y', strtotime($reg_dates[0]['value']));
            $this->reg[$year] = $reg;
            $this->regid[$year] = $regid;
        }
    }


    /**
     * Get the registration entity
     *
     */
     public function get_reg_entity($year) {
         return $this->reg[$year];
    }


  /**
   * Get reg id
   *
   * Return regid
   */
  public function get_reg_id($year){
      return $this->regid[$year];
  }


  /**
   * reg_exists
   *
   * Return boolean
   */
  public function reg_exists($year) {
      if (! empty($this->reg[$year])) return TRUE;
  }

  /**
   * reg dates
   * 
   * return the registration dates
   */
  public function get_reg_dates($year) {
      if (! empty($this->reg[$year])) {
        $reg_dates = $this->reg[$year]->get('field_registration_year')->getValue();
        return [$reg_dates[0]['value'], $reg_dates[0]['end_value']];
      }
  }  

 
  /**
   * payment received
   *
   * return boolean
   */
  public function payment_received($year) {
      if(! empty($this->reg[$year])) {
        // Check to see if payment has been received
        $payment_flag = $this->reg[$year]->get('field_payment_received')->getValue();
        return $payment_flag[0]['value'] == 1 ? TRUE : FALSE;
      }
  }

} // End of class  
