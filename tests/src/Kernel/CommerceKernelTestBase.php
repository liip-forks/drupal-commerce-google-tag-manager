<?php

namespace Drupal\Tests\commerce_google_tag_manager\Kernel;

use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase as DrupalCommerceKernelTestBase;

/**
 * Provides a base class for Commerce Google Tag Manager kernel tests.
 */
abstract class CommerceKernelTestBase extends DrupalCommerceKernelTestBase {

  /**
   * Modules to additionnaly enable.
   *
   * @var array
   */
  public static $modules = [
    'commerce_product',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_product');
    $this->installConfig(['commerce_product']);
  }

}