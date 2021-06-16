<?php

declare(strict_types = 1);

namespace Drupal\commerce_google_tag_manager\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_google_tag_manager\EventTrackerService;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event handler for commerce related events.
 */
class CommerceEventsSubscriber implements EventSubscriberInterface {

  /**
   * The Commerce GTM event tracker.
   *
   * @var \Drupal\commerce_google_tag_manager\EventTrackerService
   */
  private $eventTracker;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs the CommerceEventsSubscriber object.
   *
   * @param \Drupal\commerce_google_tag_manager\EventTrackerService $event_tracker
   *   The Commerce GTM event tracker.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match, for context.
   */
  public function __construct(EventTrackerService $event_tracker, RouteMatchInterface $route_match) {
    $this->eventTracker = $event_tracker;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CartEvents::CART_ENTITY_ADD => 'trackCartAdd',
      CartEvents::CART_ORDER_ITEM_REMOVE => 'trackCartRemove',
      'commerce_order.place.post_transition' => 'trackPurchase',
       // trackProductView should run before Dynamic Page Cache, which has
       // priority 27.
       // @see \Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber.
      KernelEvents::REQUEST => ['trackProductView', 28],
    ];
  }

  /**
   * Track the "addToCart" event.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function trackCartAdd(CartEntityAddEvent $event) {
    $this->eventTracker->addToCart($event->getOrderItem(), (int) $event->getQuantity());
  }

  /**
   * Track the "productView" event.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event to view the product.
   */
  public function trackProductView(GetResponseEvent $event) {
    $product = $this->routeMatch->getParameter('commerce_product');
    if ($event->getRequest()->getMethod() === 'GET' && !empty($product) && $this->routeMatch->getRouteName() === 'entity.commerce_product.canonical') {
      $default_variation = $product->getDefaultVariation();

      if ($default_variation) {
        // This is event has been disabled as it causes a session to be opened
        // when the user is browsing. This can increase load on the server significantly
        // as it may cause a CDN to pass requests to the server.
        // @todo Add config to allow this to be optionally enabled/disabled.
        // $this->eventTracker->productDetailViews([$default_variation]);
      }
    }
  }

  /**
   * Track the "cartRemove" event.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemRemoveEvent $event
   *   The cart event.
   */
  public function trackCartRemove(CartOrderItemRemoveEvent $event) {
    $this->eventTracker->removeFromCart($event->getOrderItem(), 1);
  }

  /**
   * Track the "purchase" event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function trackPurchase(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $this->eventTracker->purchase($order);
  }

}
