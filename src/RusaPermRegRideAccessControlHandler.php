<?php

namespace Drupal\rusa_perm_reg;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the rusa perm registration entity type.
 */
class RusaPermRegRideAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view rusa perm registration');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit rusa perm registration', 'administer rusa perm registration'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete rusa perm registration', 'administer rusa perm registration'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create rusa perm registration', 'administer rusa perm registration'], 'OR');
  }

}
