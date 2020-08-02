<?php

/**
 * Action for Views Bulk Operations
 * Fix broken result submissions
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
 *   id = "rusa_perm_reg_fix_dnf",
 *   label = @Translation("Fix mistaken DNFs"),
 *   type = ""
 * )
 */
class RideRegFixDnf extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Do some processing..

    // Don't return anything for a default completion message, otherwise return translatable markup.
    return $this->t('Some result');
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

