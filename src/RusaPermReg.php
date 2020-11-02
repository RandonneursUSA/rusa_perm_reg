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
class RusaPermReg {

    protected $storage;
    protected $reg;
    protected $regid;

    /**
     * {@inheritdoc}
     */
    public function __construct() {       

        // Get the entity query storage
		$this->storage = \Drupal::service('entity_type.manager')->getStorage('rusa_perm_registration');	
	}
	
	/**
	 * Query for program registration
	 *
	 * @param $uid = user ID
	 *
	 */
	 public function query($uid) {
		$query = $this->storage->getQuery()
            ->condition('status', 1)
            ->condition('uid', $uid);
  
        $query_result = $query->execute();

        // Store registrations keyed by year
        foreach ($query_result as $id) {
            $reg = $this->storage->load($id);
            $regid = $id;
            $reg_dates = $reg->get('field_registration_year')->getValue();
            $year = date('Y', strtotime($reg_dates[0]['value']));
            $this->reg[$year] = $reg;
            $this->regid[$year] = $regid;
        }
    }

    /**
     * Create a new program registration
     *
     * @param $uid = user id
     * @param $mid = RUSA #
     *
     * @returns the registration entity
     *
     */
    public function newProgReg($uid, $mid) {
        // Create the registration entity                
        $reg = $this->storage->create(
            [
                'uid'		  => $uid,
                'status'      => 1,
                'field_rusa_' => $mid,
                'field_registration_year' => [
                    'value'     => date("Y-m-d"),
                    'end_value' => date("Y") . '-12-31',
                ],                        
            ]);               
        $reg->save();
        return $reg;
    }

    /*
     * Check to see if user has a valid Program registration for given year
     *
     */
    public function progRegIsValid($year = NULL) {
        // Default to current year
        $year = empty($year) ? date('Y') : $year;
        return ! empty($this->reg[$year]);        
    }
    
    /**
     * Get status of this registration
     *
     * return array
     */
    public function getRegStatus($year) {
        $status['reg_exists'] = $this->reg_exists($year);
        $status['payment'] = $this->payment_received($year); 
        
        return $status;
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


    /*
     * Return the SmartWaiver URL from settings
     *
     */
    public function getSwUrl() {
        return \Drupal::config('rusa_perm_ride.settings')->get('swurl');
    }
 
 
    // Private functions
    
    /**
    * reg_exists
    *
    * Return boolean
    */
    protected function reg_exists($year) {
        return ! empty($this->reg[$year]);
    }

    /**
    * payment received
    *
    * return boolean
    */
    protected function payment_received($year) {
        if(! empty($this->reg[$year])) {
            // Check to see if payment has been received
            $payment_flag = $this->reg[$year]->get('field_payment_received')->getValue();
            return $payment_flag[0]['value'] == 1 ? TRUE : FALSE;
        }
        return FALSE;
    }

  
 } //EoC