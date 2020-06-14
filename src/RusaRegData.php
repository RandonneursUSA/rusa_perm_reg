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

		// There could be more than one
		// so check for approval
        foreach ($query_result as $id) {
            $this->reg = $storage->load($id);
            $this->regid = $id;
        	$approved_by =  $this->reg->get('field_approved_by');
			if (!empty($approved_by)) {
				break;
			}
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
   * Get current release form
   *
   * return uri of file
   */
  public function get_release_form() {
      $media = \Drupal::entityTypeManager()->getStorage('media');
      $query_result = $media->getQuery()
          ->condition('bundle', 'release_form')
          ->execute();
      if ($query_result) {
          // Justing getting the first one now
          // Need logic to select which one 
          $id = array_shift($query_result);
          $release = $media->load($id);
          $field   = $release->get('field_media_file');
          $fid     = $field[0]->getValue()['target_id'];
          $version = $release->get('field_version')[0]->getValue()['value'];;
          $file    = File::load($fid);
          $uri     = $file->get('uri')[0]->getValue()['value'];
          return(['id' => $id, 'uri' => $uri, 'version' => $version]);
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
   * Waiver exists
   *
   * Return boolean
   */
  public function waiver_exists() {
      if ($this->reg) {
        // Check to see if the waiver has been uploaded
        $this->waiver = $this->reg->get('field_signed_waiver');
        return $this->waiver->isEmpty() ? FALSE : TRUE;
      }
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
   * waiver invalid
   *
   * Return boolean
   */
  public function waiver_invalid() { 
      //Check to see if the waiver is invalid
      $waiver_file  = $this->waiver->referencedEntities();
      $invalid_flag = $waiver_file[0]->get('field_invalid')->getValue();
      return  $invalid_flag[0]['value'] == 1 ? TRUE : FALSE;
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

  /**
   * registration approved
   *
   * return FALSE or approver's name
   */
  public function registration_approved() {
      if ($this->reg) {
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
  }


} // End of class  
