<?php

namespace App\Entity;

use App\Repository\AttributeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

class Attribute
{
    // Attribute types
    const TYPE_TEXT = 'text';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_SELECT = 'select';
    const TYPE_MULTISELECT = 'multiselect';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';
    const TYPE_NUMBER = 'number';
    const TYPE_PRICE = 'price';
    const TYPE_COLOR = 'color';
    const TYPE_IMAGE = 'image';
    const TYPE_FILE = 'file';

    // Frontend input types
    const FRONTEND_INPUT_TEXT = 'text';
    const FRONTEND_INPUT_TEXTAREA = 'textarea';
    const FRONTEND_INPUT_SELECT = 'select';
    const FRONTEND_INPUT_MULTISELECT = 'multiselect';
    const FRONTEND_INPUT_CHECKBOX = 'checkbox';
    const FRONTEND_INPUT_RADIO = 'radio';
    const FRONTEND_INPUT_DATE = 'date';
    const FRONTEND_INPUT_COLOR = 'color';
    const FRONTEND_INPUT_IMAGE = 'image';
    const FRONTEND_INPUT_FILE = 'file';
    const FRONTEND_INPUT_PRICE = 'price';
    const FRONTEND_INPUT_NUMBER = 'number';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_TEXT;

    #[ORM\Column(length: 50)]
    private ?string $frontendInput = self::FRONTEND_INPUT_TEXT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $required = false;

    #[ORM\Column]
    private ?bool $filterable = false;

    #[ORM\Column]
    private ?bool $searchable = false;

    #[ORM\Column]
    private ?bool $comparable = false;

    #[ORM\Column]
    private ?bool $visibleInProductListing = false;

    #[ORM\Column]
    private ?bool $visibleOnProductPage = true;

    #[ORM\Column]
    private ?bool $usedInProductConfigurator = false;

    #[ORM\Column(nullable: true)]
    private ?int $position = 0;

    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: AttributeOption::class, orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $options;

    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: ProductAttributeValue::class, orphanRemoval: true)]
    private Collection $productAttributeValues;

    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: ProductVariationAttributeValue::class, orphanRemoval: true)]
    private Collection $variationAttributeValues;

    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: ConfigurableOption::class, orphanRemoval: true)]
    private Collection $configurableOptions;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->options = new ArrayCollection();
        $this->productAttributeValues = new ArrayCollection();
        $this->variationAttributeValues = new ArrayCollection();
        $this->configurableOptions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
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

    public function getFrontendInput(): ?string
    {
        return $this->frontendInput;
    }

    public function setFrontendInput(string $frontendInput): self
    {
        $this->frontendInput = $frontendInput;
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

    public function isRequired(): ?bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    public function isFilterable(): ?bool
    {
        return $this->filterable;
    }

    public function setFilterable(bool $filterable): self
    {
        $this->filterable = $filterable;
        return $this;
    }

    public function isSearchable(): ?bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable): self
    {
        $this->searchable = $searchable;
        return $this;
    }

    public function isComparable(): ?bool
    {
        return $this->comparable;
    }

    public function setComparable(bool $comparable): self
    {
        $this->comparable = $comparable;
        return $this;
    }

    public function isVisibleInProductListing(): ?bool
    {
        return $this->visibleInProductListing;
    }

    public function setVisibleInProductListing(bool $visibleInProductListing): self
    {
        $this->visibleInProductListing = $visibleInProductListing;
        return $this;
    }

    public function isVisibleOnProductPage(): ?bool
    {
        return $this->visibleOnProductPage;
    }

    public function setVisibleOnProductPage(bool $visibleOnProductPage): self
    {
        $this->visibleOnProductPage = $visibleOnProductPage;
        return $this;
    }

    public function isUsedInProductConfigurator(): ?bool
    {
        return $this->usedInProductConfigurator;
    }

    public function setUsedInProductConfigurator(bool $usedInProductConfigurator): self
    {
        $this->usedInProductConfigurator = $usedInProductConfigurator;
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

    /**
     * @return Collection<int, AttributeOption>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(AttributeOption $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setAttribute($this);
        }

        return $this;
    }

    public function removeOption(AttributeOption $option): self
    {
        if ($this->options->removeElement($option)) {
            // set the owning side to null (unless already changed)
            if ($option->getAttribute() === $this) {
                $option->setAttribute(null);
            }
        }

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
     * @return Collection<int, ConfigurableOption>
     */
    public function getConfigurableOptions(): Collection
    {
        return $this->configurableOptions;
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
     * Check if attribute has options (select, multiselect)
     */
    public function hasOptions(): bool
    {
        return in_array($this->type, [self::TYPE_SELECT, self::TYPE_MULTISELECT]);
    }

}