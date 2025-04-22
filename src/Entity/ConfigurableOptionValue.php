<?php

namespace App\Entity;

use App\Repository\ConfigurableOptionValueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity(repositoryClass: ConfigurableOptionValueRepository::class)]
class ConfigurableOptionValue
{
    const PRICE_TYPE_FIXED = 'fixed';
    const PRICE_TYPE_PERCENTAGE = 'percentage';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'values')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ConfigurableOption $configurableOption = null;

    #[ORM\ManyToOne(inversedBy: 'configurableOptionValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AttributeOption $option = null;

    #[ORM\Column(nullable: true)]
    private ?int $priceAdjustment = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $priceType = self::PRICE_TYPE_FIXED;

    #[ORM\Column(nullable: true)]
    private ?float $weightAdjustment = null;

    #[ORM\Column]
    private ?int $position = 0;

    #[ORM\Column]
    private ?bool $isDefault = false;

    #[ORM\OneToMany(mappedBy: 'value', targetEntity: ConfigurationRule::class, orphanRemoval: true)]
    private Collection $configurationRules;

    #[ORM\ManyToMany(targetEntity: ProductVariation::class)]
    #[ORM\JoinTable(name: 'configurable_option_value_product_variations')]
    private Collection $productVariations;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->configurationRules = new ArrayCollection();
        $this->productVariations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfigurableOption(): ?ConfigurableOption
    {
        return $this->configurableOption;
    }

    public function setConfigurableOption(?ConfigurableOption $configurableOption): self
    {
        $this->configurableOption = $configurableOption;
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

    public function getPriceAdjustment(): ?int
    {
        return $this->priceAdjustment;
    }

    public function setPriceAdjustment(?int $priceAdjustment): self
    {
        $this->priceAdjustment = $priceAdjustment;
        return $this;
    }

    /**
     * Get price adjustment as Money object
     */
    public function getPriceAdjustmentMoney(): ?Money
    {
        return $this->priceAdjustment !== null ? Money::USD($this->priceAdjustment) : null;
    }

    public function getPriceType(): ?string
    {
        return $this->priceType;
    }

    public function setPriceType(?string $priceType): self
    {
        $this->priceType = $priceType;
        return $this;
    }

    public function getWeightAdjustment(): ?float
    {
        return $this->weightAdjustment;
    }

    public function setWeightAdjustment(?float $weightAdjustment): self
    {
        $this->weightAdjustment = $weightAdjustment;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    /**
     * @return Collection<int, ConfigurationRule>
     */
    public function getConfigurationRules(): Collection
    {
        return $this->configurationRules;
    }

    public function addConfigurationRule(ConfigurationRule $rule): self
    {
        if (!$this->configurationRules->contains($rule)) {
            $this->configurationRules->add($rule);
            $rule->setValue($this);
        }

        return $this;
    }

    public function removeConfigurationRule(ConfigurationRule $rule): self
    {
        if ($this->configurationRules->removeElement($rule)) {
            // set the owning side to null (unless already changed)
            if ($rule->getValue() === $this) {
                $rule->setValue(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductVariation>
     */
    public function getProductVariations(): Collection
    {
        return $this->productVariations;
    }

    public function addProductVariation(ProductVariation $productVariation): self
    {
        if (!$this->productVariations->contains($productVariation)) {
            $this->productVariations->add($productVariation);
        }

        return $this;
    }

    public function removeProductVariation(ProductVariation $productVariation): self
    {
        $this->productVariations->removeElement($productVariation);
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
     * Calculate price adjustment based on product price
     */
    public function calculatePriceAdjustment(int $basePrice): int
    {
        if ($this->priceAdjustment === null) {
            return 0;
        }

        if ($this->priceType === self::PRICE_TYPE_PERCENTAGE) {
            // Convert percentage to actual amount
            return (int) round($basePrice * ($this->priceAdjustment / 100));
        }

        return $this->priceAdjustment;
    }
}
