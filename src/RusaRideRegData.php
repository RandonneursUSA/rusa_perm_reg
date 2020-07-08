<?php

/**
 * @file
 *  RusaRideRegData.php
 *
 * @Created 
 *  2020-06-23 - Paul Lieberman
 *
 * Get Ride Registration Data
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg;

use Drupal\rusa_api\RusaPermanents;
use Drupal\Core\Entity\Query;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
/**
 *
 */
class RusaRideRegData {

    protected $regs; // Array of registration entities

    /**
     * {@inheritdoc}
     */
    public function __construct($uid) {

        // Get ride registration data for this user
		$storage = \Drupal::service('entity_type.manager')->getStorage('rusa_perm_reg_ride');
		$query = $storage->getQuery()
            ->condition('status', 1)
            ->condition('uid', $uid);

        $query_result = $query->execute();

        // Load registrations for this user
        foreach ($query_result as $id) {
            $this->regs[] = $storage->load($id);
        }
    }

    public function get_registrations() {
        foreach ($this->regs as $reg) {
            // Get the perm data
            $pid = $reg->get('field_perm_number')->getValue()[0]['value'];
            $perm = $this->getPerm($pid);
            $data[$reg->id()] =[    
                'pid'       => $pid,
                'ride_date' => $reg->get('field_date_of_ride')->getValue()[0]['value'],
                'pname'     => $perm->name,
                'pdist'     => $perm->dist, 
                'pclimb'    => $perm->climbing, 
                'pdesc'     => $perm->description,
            ];
        }
        return($data);
    }  

    /**
     * Get perm data from rusa database
     *
     */
    public function getPerm($pid) {
        $permobj = new RusaPermanents(['key' => 'pid', 'val' => $pid]);
        $perm = $permobj->getPermanent($pid);
        return($perm);
    }


} // End of class  
