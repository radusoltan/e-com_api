<?php

namespace App\Entity;

use App\Repository\AttributeOptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeOptionRepository::class)]
class AttributeOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Attribute $attribute = null;

    #[ORM\Column(length: 255)]
    private ?string $value = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = 0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'option', targetEntity: ProductAttributeValue::class)]
    private Collection $productAttributeValues;

    #[ORM\OneToMany(mappedBy: 'option', targetEntity: ProductVariationAttributeValue::class)]
    private Collection $variationAttributeValues;

    #[ORM\OneToMany(mappedBy: 'option', targetEntity: ConfigurableOptionValue::class, orphanRemoval: true)]
    private Collection $configurableOptionValues;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->productAttributeValues = new ArrayCollection();
        $this->variationAttributeValues = new ArrayCollection();
        $this->configurableOptionValues = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label ?? $this->value;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, ProductAttributeValue>
     */
    public function getProductAttributeValues(): Collection
    {
        return $this->productAttributeValues;
    }

    /**
     * @return Collection<int, ProductVariationAttributeValue>
     */
    public function getVariationAttributeValues(): Collection
    {
        return $this->variationAttributeValues;
    }

    /**
     * @return Collection<int, ConfigurableOptionValue>
     */
    public function getConfigurableOptionValues(): Collection
    {
        return $this->configurableOptionValues;
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
}
