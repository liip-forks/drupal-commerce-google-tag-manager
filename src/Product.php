<?php

declare(strict_types = 1);

namespace Drupal\commerce_google_tag_manager;

/**
 * Represents a product in the domain of Google's Enhanced Ecommerce.
 */
class Product {

  /**
   * The product name.
   *
   * @var string
   */
  private $name;

  /**
   * Unique identifier.
   *
   * @var string
   */
  private $id;

  /**
   * The price.
   *
   * @var string
   */
  private $price;

  /**
   * The currency.
   *
   * @var string
   */
  private $currency;

  /**
   * The brand.
   *
   * @var string
   */
  private $brand;

  /**
   * Categories.
   *
   * @var string[]
   */
  private $categories = [];

  /**
   * The product variation.
   *
   * @var string
   */
  private $variant;

  /**
   * Collection of dimensions for GA.
   *
   * @var array
   */
  private $dimensions = [];

  /**
   * Collection of metrics for GA.
   *
   * @var array
   */
  private $metrics = [];

  /**
   * Build the product data as array in the requested format by Google.
   *
   * @return array
   *   Formatted Product data as requested by Google.
   */
  public function toArray() {
    $data = [];

    foreach ($this as $property => $value) {
      // Special cases for plural to singular conversion.
      $property = $property === 'categories' ? 'category' : $property;

      // Special cases without item prefix.
      $property = !in_array($property, ['price','currency'],TRUE) ? 'item_' . $property : $property;

      if (is_array($value)) {
        foreach ($value as $i => $v) {
          $propertyIndex = $i + 1;
          $singularProperty = rtrim($property, 's');
          if ($property === 'item_category') {
            // Skip category if the value is an empty string.
            if (empty($v)) {
              continue;
            }
            // For category the first item is item_category and the second
            // is item_category2.
            $propertyIdentifier = $propertyIndex === 1 ? $singularProperty : $singularProperty . $propertyIndex;
          }
          else {
            $propertyIdentifier = $singularProperty . '_' . $propertyIndex;
          }
          $data[$propertyIdentifier] = $v;
        }
      }
      elseif ($value !== NULL) {
        $data[$property] = $value;
      }
    }

    return $data;
  }

  /**
   * Get the product name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Set the product name.
   *
   * @param string $name
   *   The name.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setName($name): self {
    $this->name = $name;
    return $this;
  }

  /**
   * Get the unique identifier.
   *
   * @return string
   *   The unique identifier.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Set the unique identifier.
   *
   * @param string $id
   *   The identifier.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setId(string $id): self {
    $this->id = $id;
    return $this;
  }

  /**
   * Get the price.
   *
   * @return string
   *   The price.
   */
  public function getPrice(): string {
    return $this->price;
  }

  /**
   * Set the price.
   *
   * @param string|float $price
   *   The price.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setPrice($price): self {
    $this->price = (float) $price;
    return $this;
  }

  /**
   * Get the currency.
   *
   * @return string
   *   Currency.
   */
  public function getCurrency(): string {
    return $this->currency ?? '';
  }

  /**
   * Set the currency.
   *
   * @param string $currency
   *   The currency.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setCurrency(string $currency): self {
    $this->currency = $currency;
    return $this;
  }

  /**
   * Get the brand.
   *
   * @return string
   *   The brand.
   */
  public function getBrand(): string {
    return $this->brand;
  }

  /**
   * Set the brand.
   *
   * @param string $brand
   *   The brand.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setBrand(string $brand): self {
    $this->brand = $brand;
    return $this;
  }

  /**
   * Get the categories.
   *
   * @return string[]
   *   The categories.
   */
  public function getCategories(): array {
    return $this->categories;
  }

  /**
   * Add a category.
   *
   * @param string $category
   *   The category.
   *
   * @return $this
   */
  public function addCategory(string $category): self {
    $this->categories[] = $category;
    return $this;
  }

  /**
   * Get the variation.
   *
   * @return string
   *   The variation.
   */
  public function getVariant(): string {
    return $this->variant;
  }

  /**
   * Set the variation.
   *
   * @param string $variant
   *   The variation.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setVariant(string $variant): self {
    $this->variant = $variant;
    return $this;
  }

  /**
   * Get the collection of dimensions.
   *
   * @return string[]
   *   Collection of dimensions.
   */
  public function getDimensions(): array {
    return $this->dimensions;
  }

  /**
   * Set dimensions.
   *
   * @param array $dimensions
   *   Collection of dimensions.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setDimensions(array $dimensions): self {
    $this->dimensions = $dimensions;
    return $this;
  }

  /**
   * Get the collection of metrics.
   *
   * @return string[]
   *   Collection of metrics.
   */
  public function getMetrics(): array {
    return $this->metrics;
  }

  /**
   * Set metrics.
   *
   * @param array $metrics
   *   Collection of metrics.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function setMetrics(array $metrics): self {
    $this->metrics = $metrics;
    return $this;
  }

  /**
   * Add a custom dimension.
   *
   * @param string $dimension
   *   The dimension to add.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function addDimension(string $dimension): self {
    $this->dimensions[] = $dimension;
    return $this;
  }

  /**
   * Add a custom metric.
   *
   * @param string $metric
   *   The metric to add.
   *
   * @return \Drupal\commerce_google_tag_manager\Product
   *   The Product object.
   */
  public function addMetric(string $metric): self {
    $this->metrics[] = $metric;
    return $this;
  }

}
