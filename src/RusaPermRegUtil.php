<?php
/**
 * @file
 *  RusaPermRegUtil.php
 *
 * @Created 
 *  2025-01-06 - Jeff Loomis
 *
 * Utilities for perm registration.
 *
 * ----------------------------------------------------------------------------------------
 * 
 */

namespace Drupal\rusa_perm_reg;

use Drupal\user\Entity\User;

/**
 * RusaPermRegUtil
 *
 * Static utility functions for perm registration.
 *
 */
class RusaPermRegUtil  {

  /** 
   * Get user info
   *
   */
  static function get_user_info($current_user) {
    $user_id = $current_user->id();
    $user = User::load($user_id);

    $uinfo['uid'] = $user_id;
    $uinfo['name'] = $user->get('field_display_name')->getValue()[0]['value'];
    $uinfo['fname'] = $user->get('field_first_name')->getValue()[0]['value'];
    $uinfo['lname'] = $user->get('field_last_name')->getValue()[0]['value'];
    $dob_field = $user->get('field_date_of_birth')->getValue();
    if (isset($dob_field) && isset($dob_field[0])) {
      $uinfo['dob'] = str_replace('-', '', $dob_field[0]['value']);
    }
    else {
      $uinfo['dob'] = '';
    }
    $uinfo['mid'] = $user->get('field_rusa_member_id')->getValue()[0]['value'];
    return ($uinfo);
  }

} // End of class  

