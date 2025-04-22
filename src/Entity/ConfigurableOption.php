<?php

namespace App\Entity;

use App\Repository\ConfigurableOptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigurableOptionRepository::class)]
class ConfigurableOption
{
    const INPUT_TYPE_SELECT = 'select';
    const INPUT_TYPE_RADIO = 'radio';
    const INPUT_TYPE_CHECKBOX = 'checkbox';
    const INPUT_TYPE_COLOR = 'color';
    const INPUT_TYPE_SWATCH = 'swatch';
    const INPUT_TYPE_TEXT = 'text';
    const INPUT_TYPE_DATE = 'date';
    const INPUT_TYPE_FILE = 'file';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'configurableOptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'configurableOptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Attribute $attribute = null;

    #[ORM\Column(length: 50)]
    private ?string $inputType = self::INPUT_TYPE_SELECT;

    #[ORM\Column]
    private ?bool $required = true;

    #[ORM\Column]
    private ?int $position = 0;

    #[ORM\OneToMany(mappedBy: 'configurableOption', targetEntity: ConfigurableOptionValue::class, orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $values;

    #[ORM\OneToMany(mappedBy: 'configurableOption', targetEntity: ConfigurationRule::class, orphanRemoval: true)]
    private Collection $rules;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->values = new ArrayCollection();
        $this->rules = new ArrayCollection();
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

    public function getInputType(): ?string
    {
        return $this->inputType;
    }

    public function setInputType(string $inputType): self
    {
        $this->inputType = $inputType;
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @return Collection<int, ConfigurableOptionValue>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(ConfigurableOptionValue $value): self
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
            $value->setConfigurableOption($this);
        }

        return $this;
    }

    public function removeValue(ConfigurableOptionValue $value): self
    {
        if ($this->values->removeElement($value)) {
            // set the owning side to null (unless already changed)
            if ($value->getConfigurableOption() === $this) {
                $value->setConfigurableOption(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ConfigurationRule>
     */
    public function getRules(): Collection
    {
        return $this->rules;
    }

    public function addRule(ConfigurationRule $rule): self
    {
        if (!$this->rules->contains($rule)) {
            $this->rules->add($rule);
            $rule->setConfigurableOption($this);
        }

        return $this;
    }

    public function removeRule(ConfigurationRule $rule): self
    {
        if ($this->rules->removeElement($rule)) {
            // set the owning side to null (unless already changed)
            if ($rule->getConfigurableOption() === $this) {
                $rule->setConfigurableOption(null);
            }
        }

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
