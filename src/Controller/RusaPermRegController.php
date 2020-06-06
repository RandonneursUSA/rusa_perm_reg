<?php

namespace Drupal\rusa_perm_reg\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\rusa_api\RusaPermanents;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Messenger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RusaPermReg Controller
 *
 */
class RusaPermRegController extends ControllerBase {

  protected $mid; // Current users member id

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $current_user) {
    // Get logged in user's RUSA #
    $user = User::load($current_user->id());
    $this->mid  = $user->get('field_rusa_member_id')->getValue()[0]['value'];

    if (empty($this->mid)) {
      // This should never happen as they should get an access denied first
      $messenger = \Drupal::messenger();
      $messenger->addMessage(t("You must be logged in and have a RUSA # to use this form."), $messenger::TYPE_ERROR);
    }

  } 

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Display perm registration info
   */
  public function permRegInfo() {
    $output['prog'] = [
      '#type'   => 'details',
      '#title'  => t('Permanents Program Registration'),
    ];

    $output['prog']['info'] = [
      '#type'   => 'item',
      '#markup' => t('Info blurb goes here'),
    ];






    return $output;

  }


} //EoC
