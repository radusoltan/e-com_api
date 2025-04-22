<?php

namespace App\ValueObject;

use App\Entity\Product;
use Money\Currency;
use Money\Money;

final class ProductMoney
{

    private Money $money;
    private Product $product;
    private bool $isSpecialPrice;

    /**
     * @param Money $money The money object
     * @param Product $product The associated product
     * @param bool $isSpecialPrice Whether this is a special price
     */
    private function __construct(Money $money, Product $product, bool $isSpecialPrice = false)
    {
        $this->money = $money;
        $this->product = $product;
        $this->isSpecialPrice = $isSpecialPrice;
    }

    /**
     * Create from a product's regular price
     */
    public static function fromRegularPrice(Product $product, ?string $currencyCode = null): self
    {
        $currencyCode = $currencyCode ?? $product->getCurrencyCode() ?? 'USD';
        $amount = $product->getPrice();

        $money = new Money($amount, new Currency($currencyCode));

        return new self($money, $product, false);
    }

    /**
     * Create from a product's special price
     */
    public static function fromSpecialPrice(Product $product, ?string $currencyCode = null): ?self
    {
        if (!$product->hasValidSpecialPrice()) {
            return null;
        }

        $currencyCode = $currencyCode ?? $product->getCurrencyCode() ?? 'USD';
        $amount = $product->getSpecialPrice();

        if ($amount === null) {
            return null;
        }

        $money = new Money($amount, new Currency($currencyCode));

        return new self($money, $product, true);
    }

    /**
     * Create from a product's current price (regular or special)
     */
    public static function fromCurrentPrice(Product $product, ?string $currencyCode = null): self
    {
        $currencyCode = $currencyCode ?? $product->getCurrencyCode() ?? 'USD';
        $isSpecialPrice = $product->hasValidSpecialPrice();
        $amount = $isSpecialPrice ? $product->getSpecialPrice() : $product->getPrice();

        $money = new Money($amount, new Currency($currencyCode));

        return new self($money, $product, $isSpecialPrice);
    }

    /**
     * Get the wrapped Money object
     */
    public function getMoney(): Money
    {
        return $this->money;
    }

    /**
     * Get the amount in minor units (cents)
     */
    public function getAmount(): int
    {
        return (int) $this->money->getAmount();
    }

    /**
     * Get the amount in major units (dollars)
     */
    public function getAmountAsMajorUnits(): float
    {
        return (float) $this->money->getAmount() / 100;
    }

    /**
     * Get the currency
     */
    public function getCurrency(): Currency
    {
        return $this->money->getCurrency();
    }

    /**
     * Get the currency code
     */
    public function getCurrencyCode(): string
    {
        return $this->money->getCurrency()->getCode();
    }

    /**
     * Check if this is a special price
     */
    public function isSpecialPrice(): bool
    {
        return $this->isSpecialPrice;
    }

    /**
     * Format the money with currency symbol
     */
    public function format(?string $locale = null): string
    {
        $locale = $locale ?? 'en_US';
        $numberFormatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $numberFormatter->formatCurrency(
            $this->getAmountAsMajorUnits(),
            $this->getCurrencyCode()
        );
    }

    /**
     * Add another amount to this money
     */
    public function add(self|Money|int $amount): self
    {
        if ($amount instanceof self) {
            $newMoney = $this->money->add($amount->getMoney());
        } elseif ($amount instanceof Money) {
            $newMoney = $this->money->add($amount);
        } else {
            $newMoney = $this->money->add(new Money($amount, $this->getCurrency()));
        }

        return new self($newMoney, $this->product, $this->isSpecialPrice);
    }

    /**
     * Subtract another amount from this money
     */
    public function subtract(self|Money|int $amount): self
    {
        if ($amount instanceof self) {
            $newMoney = $this->money->subtract($amount->getMoney());
        } elseif ($amount instanceof Money) {
            $newMoney = $this->money->subtract($amount);
        } else {
            $newMoney = $this->money->subtract(new Money($amount, $this->getCurrency()));
        }

        return new self($newMoney, $this->product, $this->isSpecialPrice);
    }

    /**
     * Multiply this money by a factor
     */
    public function multiply(float $factor): self
    {
        $newMoney = $this->money->multiply((string)$factor);

        return new self($newMoney, $this->product, $this->isSpecialPrice);
    }

    /**
     * Get the percentage difference between regular and special price
     */
    public static function getDiscountPercentage(Product $product): ?float
    {
        if (!$product->hasValidSpecialPrice()) {
            return null;
        }

        $regularPrice = $product->getPrice();
        $specialPrice = $product->getSpecialPrice();

        if ($regularPrice <= 0 || $specialPrice === null) {
            return null;
        }

        return round((($regularPrice - $specialPrice) / $regularPrice) * 100, 2);
    }

    /**
     * Convert to string (formatted amount)
     */
    public function __toString(): string
    {
        return $this->format();
    }

}