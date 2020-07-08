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


use Drupal\Core\Entity\Query;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
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

      
        // Get a date string suitable for use with entity query.
        $date = new DrupalDateTime();
        $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

        // Get registration data
        // Query should only get registrations for the current year
		$storage = \Drupal::service('entity_type.manager')->getStorage('rusa_perm_registration');
		$query = $storage->getQuery()
            ->condition('status', 1)
            ->condition('uid', $this->uid)
  			->condition('field_registration_year.value', $date, '<=')
  			->condition('field_registration_year.end_value', $date, '>');

        $query_result = $query->execute();

        foreach ($query_result as $id) {
            $this->reg = $storage->load($id);
            $this->regid = $id;
        }
    }


    /**
     * Get the registration entity
     *
     */
     public function get_reg_entity() {
         return $this->reg;
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
      if ($this->reg) return TRUE;
  }

  /**
   * reg dates
   * 
   * return the registration dates
   */
  public function get_reg_dates() {
      if ($this->reg) {
        $reg_dates = $this->reg->get('field_registration_year')->getValue();
        return [$reg_dates[0]['value'], $reg_dates[0]['end_value']];
      }
  }  

 
  /**
   * payment received
   *
   * return boolean
   */
  public function payment_received() {
      if ($this->reg) {
        // Check to see if payment has been received
        $payment_flag = $this->reg->get('field_payment_received')->getValue();
        return $payment_flag[0]['value'] == 1 ? TRUE : FALSE;
      }
  }

} // End of class  
