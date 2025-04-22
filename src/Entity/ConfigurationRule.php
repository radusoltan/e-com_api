<?php

namespace App\Entity;

use App\Repository\ConfigurationRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigurationRuleRepository::class)]
class ConfigurationRule
{
    const TYPE_DEPENDENCY = 'dependency';
    const TYPE_EXCLUSION = 'exclusion';
    const TYPE_REQUIREMENT = 'requirement';
    const TYPE_PRICE_ADJUSTMENT = 'price_adjustment';
    const TYPE_WEIGHT_ADJUSTMENT = 'weight_adjustment';
    const TYPE_VALIDATION = 'validation';
    const TYPE_CUSTOM = 'custom';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ConfigurableOption $configurableOption = null;

    #[ORM\ManyToOne(inversedBy: 'configurationRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ConfigurableOptionValue $value = null;

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_DEPENDENCY;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetOptionCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetValueCode = null;

    #[ORM\Column]
    private ?int $sortOrder = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customValidation = null;

    #[ORM\Column(nullable: true)]
    private ?int $priceAdjustment = null;

    #[ORM\Column(nullable: true)]
    private ?float $weightAdjustment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $errorMessage = null;

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

    public function getConfigurableOption(): ?ConfigurableOption
    {
        return $this->configurableOption;
    }

    public function setConfigurableOption(?ConfigurableOption $configurableOption): self
    {
        $this->configurableOption = $configurableOption;
        return $this;
    }

    public function getValue(): ?ConfigurableOptionValue
    {
        return $this->value;
    }

    public function setValue(?ConfigurableOptionValue $value): self
    {
        $this->value = $value;
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

    public function getTargetOptionCode(): ?string
    {
        return $this->targetOptionCode;
    }

    public function setTargetOptionCode(?string $targetOptionCode): self
    {
        $this->targetOptionCode = $targetOptionCode;
        return $this;
    }

    public function getTargetValueCode(): ?string
    {
        return $this->targetValueCode;
    }

    public function setTargetValueCode(?string $targetValueCode): self
    {
        $this->targetValueCode = $targetValueCode;
        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getCustomValidation(): ?string
    {
        return $this->customValidation;
    }

    public function setCustomValidation(?string $customValidation): self
    {
        $this->customValidation = $customValidation;
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

    public function getWeightAdjustment(): ?float
    {
        return $this->weightAdjustment;
    }

    public function setWeightAdjustment(?float $weightAdjustment): self
    {
        $this->weightAdjustment = $weightAdjustment;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
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
     * Check if rule is a dependency type
     */
    public function isDependency(): bool
    {
        return $this->type === self::TYPE_DEPENDENCY;
    }

    /**
     * Check if rule is an exclusion type
     */
    public function isExclusion(): bool
    {
        return $this->type === self::TYPE_EXCLUSION;
    }

    /**
     * Check if rule is a requirement type
     */
    public function isRequirement(): bool
    {
        return $this->type === self::TYPE_REQUIREMENT;
    }

    /**
     * Check if rule is a validation type
     */
    public function isValidation(): bool
    {
        return $this->type === self::TYPE_VALIDATION;
    }
}
