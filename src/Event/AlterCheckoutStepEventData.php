<?php

declare(strict_types = 1);

namespace Drupal\commerce_google_tag_manager\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event dispatched when a checkout step is tracked.
 *
 * Event listeners may use this event to track checkout options.
 */
class AlterCheckoutStepEventData extends Event {

  /**
   * The checkout step number.
   *
   * @var int
   */
  private $stepIndex;

  /**
   * The order entity.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  private $order;

  /**
   * The event data.
   *
   * @var
   */
  private $data;

  /**
   * Constructs a TrackCheckoutStepEvent object.
   *
   * @param int $step_index
   *   The step index of the checkout step, starting at 1.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order representing the current cart.
   */
  public function __construct(
    $step_index,
    OrderInterface $order,
    array $data
  ) {
    $this->stepIndex = $step_index;
    $this->order = $order;
    $this->data = $data;
  }

  /**
   * Get the step index.
   *
   * @return int
   *   The step number.
   */
  public function getStepIndex() {
    return $this->stepIndex;
  }

  /**
   * Get the Checkout current order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order entity.
   */
  public function getOrder() {
    return $this->order;
  }

  /**
   * Get the event data.
   *
   * @return array
   *   Event data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Set the event data.
   *
   * @param array $data
   *   Event data.
   *
   * @return $this
   */
  public function setData(array $data) {
    $this->data = $data;
  }

}
