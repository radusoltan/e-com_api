<?php

namespace App\Entity\Trait;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait ProductPricingTrait
{

    #[ORM\Column(type: 'integer')]
    private ?int $price = null; // Stored in cents

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $specialPrice = null; // Stored in cents

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $specialPriceFrom = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $specialPriceTo = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currencyCode = null; // ISO currency code, nullable for backward compatibility

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getSpecialPrice(): ?int
    {
        return $this->specialPrice;
    }

    public function setSpecialPrice(?int $specialPrice): self
    {
        $this->specialPrice = $specialPrice;
        return $this;
    }

    public function getSpecialPriceFrom(): ?\DateTimeImmutable
    {
        return $this->specialPriceFrom;
    }

    public function setSpecialPriceFrom(?\DateTimeImmutable $specialPriceFrom): self
    {
        $this->specialPriceFrom = $specialPriceFrom;
        return $this;
    }

    public function getSpecialPriceTo(): ?\DateTimeImmutable
    {
        return $this->specialPriceTo;
    }

    public function setSpecialPriceTo(?\DateTimeImmutable $specialPriceTo): self
    {
        $this->specialPriceTo = $specialPriceTo;
        return $this;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(?string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;
        return $this;
    }

    /**
     * Check if product has a valid special price
     */
    public function hasValidSpecialPrice(): bool
    {
        if ($this->specialPrice === null) {
            return false;
        }

        $now = new \DateTimeImmutable();

        // Check from date if set
        if ($this->specialPriceFrom !== null && $now < $this->specialPriceFrom) {
            return false;
        }

        // Check to date if set
        if ($this->specialPriceTo !== null && $now > $this->specialPriceTo) {
            return false;
        }

        return true;
    }

}