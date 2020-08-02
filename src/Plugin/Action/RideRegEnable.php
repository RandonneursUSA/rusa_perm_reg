<?php

/**
 * Action for Views Bulk Operations
 * Enable a ride registrtion
 *
 *
 */

namespace Drupal\rusa_perm_reg\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Action description.
 *
 * @Action(
 *   id = "rusa_perm_reg_ride_enable",
 *   label = @Translation("Enable Ride Reg"),
 *   type = ""
 * )
 */
class RideRegEnable extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity->set('status', 1);
    $entity->save();

    // Don't return anything for a default completion message, otherwise return translatable markup.
    return $this->t('Ride registrations enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

}

