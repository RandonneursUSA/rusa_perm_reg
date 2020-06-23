<?php

namespace Drupal\rusa_perm_reg\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drupal\smartwaiver\Event\SmartwaiverEvent;
// use Drupal\smartwaiver\Service\Client;
// use Drupal\smartwaiver\Service\AuthenticWebhook;



/**
 * Event subscriptions for events dispatched by Smartwaive
 */
class RusaPermRegSubscriber implements EventSubscriberInterface {

    protected $swaiver;

    /**
     * Constructor.
     *
     */
    public function __construct() {
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array
     *   The event names to listen to
     */
    static function getSubscribedEvents() {
        return ['SmartwaiverEvent::NEW_WAIVER' => 'new_waiver'];
    }

    protected function new_waiver($waiver) {
        dpm($waiver);
    }   

}
