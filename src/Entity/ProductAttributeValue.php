<?php

namespace App\Entity;

use App\Repository\ProductAttributeValueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeValueRepository::class)]
#[ORM\UniqueConstraint(
    name: 'unique_product_attribute',
    columns: ['product_id', 'attribute_id']
)]
class ProductAttributeValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attributeValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productAttributeValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Attribute $attribute = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\ManyToOne(inversedBy: 'productAttributeValues')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AttributeOption $option = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getAttribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(?Attribute $attribute): self
    {
        $this->attribute = $attribute;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getOption(): ?AttributeOption
    {
        return $this->option;
    }

    public function setOption(?AttributeOption $option): self
    {
        $this->option = $option;
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
     * Get the appropriate display value based on attribute type
     */
    public function getDisplayValue(): ?string
    {
        // For select/multiselect attributes, return the option label
        if ($this->attribute->hasOptions() && $this->option !== null) {
            return $this->option->getLabel();
        }

        // For boolean attributes
        if ($this->attribute->getType() === Attribute::TYPE_BOOLEAN) {
            return $this->value === '1' ? 'Yes' : 'No';
        }

        // For all other attribute types
        return $this->value;
    }
}
