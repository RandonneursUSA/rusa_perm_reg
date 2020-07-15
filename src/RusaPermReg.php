<?php
/**
 * Static functions for other modules to get perm reg data
 *
 */



namespace Drupal\rusa_perm_reg;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

 
 class RusaPermReg {
 
    // No constructor because we are not calling new
 
 
    /*
     * Check to see if user has a valid Program registration
     *
     */
    public static function progRegIsValid($uid) {
    
        // Get a date string suitable for use with entity query.
        $date = new DrupalDateTime();
        $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

        // Get registration data
        // Query should only get registrations for the current year
        $storage = \Drupal::service('entity_type.manager')->getStorage('rusa_perm_registration');
        $query = $storage->getQuery()
            ->condition('status', 1)
            ->condition('uid', $uid)
            ->condition('field_registration_year.value', $date, '<=')
            ->condition('field_registration_year.end_value', $date, '>');

        $query_result = $query->execute();

        return $query_result;      
        
    }


    /*
     * Return the SmartWaiver URL from settings
     *
     */
    public static function getSwUrl() {
        return \Drupal::config('rusa_perm_ride.settings')->get('swurl');
    }
 
 
 
 
 
 
 } //EoC