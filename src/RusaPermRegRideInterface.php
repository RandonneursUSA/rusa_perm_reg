<?php

namespace Drupal\rusa_perm_reg;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a rusa perm registration entity type.
 */
interface RusaPermRegRideInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the rusa perm registration creation timestamp.
   *
   * @return int
   *   Creation timestamp of the rusa perm registration.
   */
  public function getCreatedTime();

  /**
   * Sets the rusa perm registration creation timestamp.
   *
   * @param int $timestamp
   *   The rusa perm registration creation timestamp.
   *
   * @return \Drupal\rusa_perm_reg\RusaPermRegRideInterface
   *   The called rusa perm registration entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the rusa perm registration status.
   *
   * @return bool
   *   TRUE if the rusa perm registration is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the rusa perm registration status.
   *
   * @param bool $status
   *   TRUE to enable this rusa perm registration, FALSE to disable.
   *
   * @return \Drupal\rusa_perm_reg\RusaPermRegRideInterface
   *   The called rusa perm registration entity.
   */
  public function setStatus($status);

}
