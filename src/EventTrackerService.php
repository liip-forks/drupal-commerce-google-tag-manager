<?php

declare(strict_types = 1);

namespace Drupal\commerce_google_tag_manager;

use Drupal\commerce\Context;
use Drupal\commerce_google_tag_manager\Event\AlterCheckoutStepEventData;
use Drupal\commerce_google_tag_manager\Event\AlterProductEvent;
use Drupal\commerce_google_tag_manager\Event\AlterProductPurchasedEntityEvent;
use Drupal\commerce_google_tag_manager\Event\EnhancedEcommerceEvents;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\PriceCalculatorInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Track different events from Google's Enhanced Ecommerce.
 *
 * @see https://developers.google.com/tag-manager/enhanced-ecommerce
 */
class EventTrackerService {

  const EVENT_PRODUCT_IMPRESSIONS = 'view_item_list';
  const EVENT_PRODUCT_DETAIL_VIEWS = 'view_item';
  const EVENT_PRODUCT_CLICK = 'select_item';
  const EVENT_ADD_CART = 'add_to_cart';
  const EVENT_REMOVE_CART = 'remove_from_cart';
  const EVENT_BEGIN_CHECKOUT = 'begin_checkout';
  const EVENT_ADD_SHIPPING_INFO = 'add_shipping_info';
  const EVENT_ADD_PAYMENT_INFO = 'add_payment_info';
  const EVENT_PURCHASE = 'purchase';

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The Commerce GTM event storage.
   *
   * @var \Drupal\commerce_google_tag_manager\EventStorageService
   */
  private $eventStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The price calculator.
   *
   * @var \Drupal\commerce_order\PriceCalculatorInterface
   */
  protected $priceCalculator;

  /**
   * Constructs the EventTrackerService service.
   *
   * @param \Drupal\commerce_google_tag_manager\EventStorageService $event_storage
   *   The Commerce GTM event storage.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\commerce_order\PriceCalculatorInterface $price_calculator
   *   The price calculator.
   */
  public function __construct(EventStorageService $event_storage,
                              EventDispatcherInterface $event_dispatcher,
                              CurrentStoreInterface $current_store,
                              AccountInterface $current_user,
                              PriceCalculatorInterface $price_calculator) {
    $this->eventDispatcher = $event_dispatcher;
    $this->eventStorage = $event_storage;
    $this->currentStore = $current_store;
    $this->currentUser = $current_user;
    $this->priceCalculator = $price_calculator;
  }

  /**
   * Track product impressions.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $product_variations
   *   The commerce product variation entities being viewed.
   * @param string $list
   *   The name of the list showing the products.
   */
  public function productImpressions(array $product_variations, $list = '') {
    $products_data = array_map(function ($product_variation) use ($list) {
      return array_merge(
        $this->buildProductFromProductVariation($product_variation)->toArray(),
        ['item_list_name' => $list]);
    }, $product_variations);

    $data = [
      'event' => self::EVENT_PRODUCT_IMPRESSIONS,
      'ecommerce' => [
        'items' => $products_data,
      ],
    ];

    $this->eventStorage->addEvent($data);
  }

  /**
   * Track product detail views.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariation[] $product_variations
   *   The commerce product variations being viewed.
   * @param string $list
   *   An optional name of a list.
   */
  public function productDetailViews(array $product_variations, $list = '') {
    $data = [
      'event' => self::EVENT_PRODUCT_DETAIL_VIEWS,
      'ecommerce' => [
        'items' => array_merge(
          $this->buildProductsFromProductVariations($product_variations),
          ['item_list_name' => $list]
        ),
      ],
    ];

    $this->eventStorage->addEvent($data);
  }

  /**
   * Track a "product click" event.
   *
   * @param array $product_variations
   *   A commerce product variation that was clicked.
   * @param string $list
   *   An optional name of a list.
   */
  public function productClick(array $product_variations, $list = '') {
    $data = [
      'event' => self::EVENT_PRODUCT_CLICK,
      'ecommerce' => [
        'items' => array_merge(
          $this->buildProductsFromProductVariations($product_variations),
          ['item_list_name' => $list]
        ),
      ],
    ];

    $this->eventStorage->addEvent($data);
  }

  /**
   * Track the "addToCart" event.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The oder item added to the cart.
   * @param int $quantity
   *   Quantity added to cart.
   */
  public function addToCart(OrderItemInterface $order_item, $quantity) {
    $product = $this->buildProductFromOrderItem($order_item);

    $data = [
      'event' => self::EVENT_ADD_CART,
      'ecommerce' => [
        'items' => [
          array_merge($product->toArray(), ['quantity' => $quantity]),
        ],
      ],
    ];

    $this->eventStorage->addEvent($data);
  }

  /**
   * Track the "removeFromCart" event.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The commerce order item removed from the cart.
   * @param int $quantity
   *   The removed quantity.
   */
  public function removeFromCart(OrderItemInterface $order_item, $quantity) {
    $product = $this->buildProductFromOrderItem($order_item);

    $data = [
      'event' => self::EVENT_REMOVE_CART,
      'ecommerce' => [
        'items' => [
          array_merge($product->toArray(), ['quantity' => $quantity]),
        ],
      ],
    ];

    $this->eventStorage->addEvent($data);
  }

  /**
   * Track a checkout step.
   *
   * @param int $step_index
   *   The index of the checkout step (1-based).
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order representing the cart.
   */
  public function checkoutStep($step_index, OrderInterface $order) {
    $data = [
      'event' => NULL,
      'ecommerce' => [
        'items' => $this->buildProductsFromOrderItems($order->getItems()),
        ],
      ];

    $event = new AlterCheckoutStepEventData(
      $step_index,
      $order,
      $data
    );

    $this->eventDispatcher->dispatch($event, EnhancedEcommerceEvents::ALTER_CHECKOUT_STEP_EVENT_DATA);
    $data = $event->getData();

    // The event is only dispatched if the event name has been added by a
    // ALTER_CHECKOUT_STEP_EVENT_DATA event subscriber.
    if (isset($data['event'])) {
      $this->eventStorage->addEvent($data);
    }
  }

  /**
   * Track a purchase of the given order entity.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order entity.
   */
  public function purchase(OrderInterface $order) {
    $data = [
      'event' => self::EVENT_PURCHASE,
      'ecommerce' => [
        'transaction_id' => (int) $order->getOrderNumber(),
        'affiliation' => $order->getStore()->getName(),
        // The value should be the total value (incl. tax and shipping).
        'value' => (float) self::formatPrice($order->getTotalPrice()->getNumber()),
        'tax' => (float) self::formatPrice((string) $this->calculateTax($order)),
        'shipping' => (float) self::formatPrice((string) $this->calculateShipping($order)),
        'currency' => $order->getTotalPrice()->getCurrencyCode(),
        'coupon' => $this->getCouponCode($order),
        'items' => $this->buildProductsFromOrderItems($order->getItems()),
        ],
    ];

    $this->eventStorage->addEvent($data);
  }

  /**
   * Build the Enhanced Ecommerce product from a given commerce order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   A commerce order item.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Enhanced Ecommerce product.
   */
  private function buildProductFromOrderItem(OrderItemInterface $order_item) {
    $purchased_entity = $order_item->getPurchasedEntity();

    if ($purchased_entity instanceof ProductVariationInterface) {
      $product = $this->buildProductFromProductVariation($purchased_entity);
    }
    else {
      // The purchased entity is not a product variation.
      $product = (new Product())
        ->setName($order_item->getTitle())
        ->setId((string) $order_item->getPurchasedEntityId())
        ->setPrice((float) self::formatPrice($order_item->getTotalPrice()->getNumber()))
        ->setCurrency($order_item->getUnitPrice()->getCurrencyCode());
    }

    $event = new AlterProductPurchasedEntityEvent($product, $order_item, $purchased_entity);
    $this->eventDispatcher->dispatch($event, EnhancedEcommerceEvents::ALTER_PRODUCT_PURCHASED_ENTITY);

    return $product;
  }

  /**
   * Build Enhanced Ecommerce product from a given commerce product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation
   *   A commerce product variation.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Enhanced Ecommerce product.
   */
  private function buildProductFromProductVariation(ProductVariationInterface $product_variation) {
    $context = new Context($this->currentUser, $this->currentStore->getStore());

    $product = new Product();
    $product
      ->setName($product_variation->getProduct()->getTitle())
      ->setId((string) $product_variation->getProduct()->id())
      ->setVariant($product_variation->getTitle());

    // Get price based on resolver(s).
    /** @var \Drupal\commerce_price\Price $calculated_price */
    $calculated_price = $this->priceCalculator->calculate($product_variation, 1, $context)->getCalculatedPrice();
    if ($calculated_price) {
      $product
        ->setPrice((float) self::formatPrice((float) $calculated_price->getNumber()))
        ->setCurrency($calculated_price->getCurrencyCode());
    }

    $event = new AlterProductEvent($product, $product_variation);
    $this->eventDispatcher->dispatch($event, EnhancedEcommerceEvents::ALTER_PRODUCT);

    return $product;
  }

  /**
   * Build the Enhanced Ecommerce products from given commerce order items.
   *
   * @param array $order_items
   *   The commerce order items.
   *
   * @return array
   *   An array of EnhancedEcommerce products.
   */
  private function buildProductsFromOrderItems(array $order_items) {
    return array_map(function ($order_item) {
      return array_merge(
        $this->buildProductFromOrderItem($order_item)->toArray(),
        ['quantity' => (int) $order_item->getQuantity()]
      );
    }, $order_items);
  }

  /**
   * Build Enhanced Ecommerce products from given commerce product variations.
   *
   * @param array $product_variations
   *   The commerce product variations.
   *
   * @return array
   *   An array of EnhancedEcommerce products.
   */
  private function buildProductsFromProductVariations(array $product_variations) {
    return array_map(function ($product_variation) {
      return $this
        ->buildProductFromProductVariation($product_variation)
        ->toArray();
    }, $product_variations);
  }

  /**
   * Format the given price into a compliant Google's Enhanced Ecommerce.
   *
   * The given price will be truncate to contain only 2 decimals.
   * No round up are operate, so 11,999 will become 11,99.
   *
   * @param mixed $price
   *   The price to format.
   *
   * @return string
   *   The formatted price.
   */
  public static function formatPrice($price): string {

    if (!is_numeric($price)) {
      throw new \InvalidArgumentException("The string must be a numeric.");
    }

    if ($price == 0.0) {
      return '0';
    }

    // Truncate decimals without rounding.
    $number = bcdiv((string) $price, '1', 2);

    // Format the number as requested by Google's Enhanced Ecommerce.
    return number_format((float) $number, 2, '.', '');
  }

  /**
   * Calculate the tax costs from the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order containing potential tax.
   *
   * @return float
   *   The tax costs.
   */
  private function calculateTax(OrderInterface $order) {
    $tax_adjustments = array_filter($order->collectAdjustments(), function (Adjustment $adjustment) {
      return ($adjustment->getType() === 'tax') && (!empty($adjustment->getSourceId()));
    });

    $total = 0;
    /** @var \Drupal\commerce_order\Adjustment $tax_adjustment */
    foreach ($tax_adjustments as $tax_adjustment) {
      $total += (float) $tax_adjustment->getAmount()->getNumber();
    }

    return $total;
  }

  /**
   * Calculate the total shipping costs from the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order containing potential shipping.
   *
   * @return float
   *   The shipping total price.
   */
  private function calculateShipping(OrderInterface $order) {
    if ($order->hasField('shipments') && !$order->get('shipments')->isEmpty()) {
      $total = 0;
      foreach ($order->get('shipments')->referencedEntities() as $shipment) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        $total += (float) $shipment->getAmount()->getNumber();
      }

      return $total;
    }

    return 0;
  }

  /**
   * Get the coupon code(s) used with the given commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order containing potential coupon code(s).
   *
   * @return string
   *   The coupon values separated by comma.
   */
  private function getCouponCode(OrderInterface $order) {
    if (!$order->hasField('coupons') || $order->get('coupons')->isEmpty()) {
      return '';
    }

    $coupon_codes = array_map(function ($coupon) {
      /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
      return $coupon->getCode();
    }, $order->get('coupons')->referencedEntities());

    if (count($coupon_codes) === 1) {
      return $coupon_codes[0];
    }

    return implode(', ', $coupon_codes);
  }

}
