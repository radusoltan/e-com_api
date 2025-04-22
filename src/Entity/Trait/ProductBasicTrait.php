<?php

namespace App\Entity\Trait;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait ProductBasicTrait
{

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $sku = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_SIMPLE;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column]
    private ?bool $featured = false;

    #[ORM\Column(nullable: true)]
    private ?float $weight = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function isFeatured(): ?bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): self
    {
        $this->featured = $featured;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * Check if product is configurable
     */
    public function isConfigurable(): bool
    {
        return $this->type === self::TYPE_CONFIGURABLE;
    }

    /**
     * Check if product is simple
     */
    public function isSimple(): bool
    {
        return $this->type === self::TYPE_SIMPLE;
    }

    /**
     * Check if product is virtual
     */
    public function isVirtual(): bool
    {
        return $this->type === self::TYPE_VIRTUAL;
    }

    /**
     * Check if product is downloadable
     */
    public function isDownloadable(): bool
    {
        return $this->type === self::TYPE_DOWNLOADABLE;
    }

    /**
     * Check if product is bundle
     */
    public function isBundle(): bool
    {
        return $this->type === self::TYPE_BUNDLE;
    }

}