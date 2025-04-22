<?php

namespace App\Entity;

use App\Repository\ProductVariationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity(repositoryClass: ProductVariationRepository::class)]
class ProductVariation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'variations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $parent = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $sku = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $price = null; // Stored in cents

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $specialPrice = null; // Stored in cents

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $specialPriceFrom = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $specialPriceTo = null;

    #[ORM\Column(nullable: true)]
    private ?float $weight = null;

    #[ORM\OneToMany(mappedBy: 'variation', targetEntity: ProductVariationAttributeValue::class, orphanRemoval: true)]
    private Collection $attributeValues;

    #[ORM\OneToMany(mappedBy: 'productVariation', targetEntity: ProductInventory::class, orphanRemoval: true)]
    private Collection $inventories;

    #[ORM\OneToMany(mappedBy: 'variation', targetEntity: Image::class)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->attributeValues = new ArrayCollection();
        $this->inventories = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?Product
    {
        return $this->parent;
    }

    public function setParent(?Product $parent): self
    {
        $this->parent = $parent;

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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get price as a Money object.
     */
    public function getPriceMoney(): ?Money
    {
        return null !== $this->price ? Money::USD($this->price) : null;
    }

    /**
     * Get effective price - uses parent product price if variation price is null.
     */
    public function getEffectivePrice(): int
    {
        if ($this->hasValidSpecialPrice()) {
            return $this->specialPrice;
        }

        if (null !== $this->price) {
            return $this->price;
        }

        return $this->parent->getCurrentPrice();
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

    /**
     * Get special price as a Money object.
     */
    public function getSpecialPriceMoney(): ?Money
    {
        return null !== $this->specialPrice ? Money::USD($this->specialPrice) : null;
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
     * Get effective weight - uses parent product weight if variation weight is null.
     */
    public function getEffectiveWeight(): ?float
    {
        if (null !== $this->weight) {
            return $this->weight;
        }

        return $this->parent->getWeight();
    }

    /**
     * @return Collection<int, ProductVariationAttributeValue>
     */
    public function getAttributeValues(): Collection
    {
        return $this->attributeValues;
    }

    public function addAttributeValue(ProductVariationAttributeValue $attributeValue): self
    {
        if (!$this->attributeValues->contains($attributeValue)) {
            $this->attributeValues->add($attributeValue);
            $attributeValue->setVariation($this);
        }

        return $this;
    }

    public function removeAttributeValue(ProductVariationAttributeValue $attributeValue): self
    {
        if ($this->attributeValues->removeElement($attributeValue)) {
            // set the owning side to null (unless already changed)
            if ($attributeValue->getVariation() === $this) {
                $attributeValue->setVariation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductInventory>
     */
    public function getInventories(): Collection
    {
        return $this->inventories;
    }

    public function addInventory(ProductInventory $inventory): self
    {
        if (!$this->inventories->contains($inventory)) {
            $this->inventories->add($inventory);
            $inventory->setProductVariation($this);
        }

        return $this;
    }

    public function removeInventory(ProductInventory $inventory): self
    {
        if ($this->inventories->removeElement($inventory)) {
            // set the owning side to null (unless already changed)
            if ($inventory->getProductVariation() === $this) {
                $inventory->setProductVariation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setVariation($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getVariation() === $this) {
                $image->setVariation(null);
            }
        }

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Check if the variation has a valid special price.
     */
    public function hasValidSpecialPrice(): bool
    {
        if (null === $this->specialPrice) {
            return false;
        }

        $now = new \DateTimeImmutable();

        // Check from date if set
        if (null !== $this->specialPriceFrom && $now < $this->specialPriceFrom) {
            return false;
        }

        // Check to date if set
        if (null !== $this->specialPriceTo && $now > $this->specialPriceTo) {
            return false;
        }

        return true;
    }
}
