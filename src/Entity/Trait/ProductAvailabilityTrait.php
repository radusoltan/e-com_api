<?php

namespace App\Entity\Trait;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait ProductAvailabilityTrait
{

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $availableFrom = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $availableTo = null;

    public function getAvailableFrom(): ?\DateTimeImmutable
    {
        return $this->availableFrom;
    }

    public function setAvailableFrom(?\DateTimeImmutable $availableFrom): self
    {
        $this->availableFrom = $availableFrom;
        return $this;
    }

    public function getAvailableTo(): ?\DateTimeImmutable
    {
        return $this->availableTo;
    }

    public function setAvailableTo(?\DateTimeImmutable $availableTo): self
    {
        $this->availableTo = $availableTo;
        return $this;
    }

    /**
     * Check if product is available based on date range
     */
    public function isAvailable(): bool
    {
        $now = new \DateTimeImmutable();

        // If no date restrictions, product is available
        if ($this->availableFrom === null && $this->availableTo === null) {
            return true;
        }

        // Check from date if set
        if ($this->availableFrom !== null && $now < $this->availableFrom) {
            return false;
        }

        // Check to date if set
        if ($this->availableTo !== null && $now > $this->availableTo) {
            return false;
        }

        return true;
    }

}