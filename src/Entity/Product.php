<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{

    const TYPE_SIMPLE = 'simple';
    const TYPE_CONFIGURABLE = 'configurable';
    const TYPE_VIRTUAL = 'virtual';
    const TYPE_DOWNLOADABLE = 'downloadable';
    const TYPE_BUNDLE = 'bundle';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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

    #[ORM\Column(type: 'integer')]
    private ?int $price = null; // Stored in cents

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $specialPrice = null; // Stored in cents

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $specialPriceFrom = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $specialPriceTo = null;

    #[ORM\Column(nullable: true)]
    private ?float $weight = null;

    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_categories')]
    private Collection $categories;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Image::class, orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttributeValue::class, orphanRemoval: true)]
    private Collection $attributeValues;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_tags')]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: ProductVariation::class, orphanRemoval: true)]
    private Collection $variations;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductInventory::class, orphanRemoval: true)]
    private Collection $inventories;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ConfigurableOption::class, orphanRemoval: true)]
    private Collection $configurableOptions;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $availableFrom = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $availableTo = null;

    #[ORM\ManyToMany(targetEntity: Product::class, inversedBy: 'relatedProducts')]
    #[ORM\JoinTable(name: 'product_related')]
    private Collection $relatedTo;

    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'relatedTo')]
    private Collection $relatedProducts;

    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'product_cross_sells')]
    private Collection $crossSells;

    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'product_up_sells')]
    private Collection $upSells;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->attributeValues = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->variations = new ArrayCollection();
        $this->inventories = new ArrayCollection();
        $this->configurableOptions = new ArrayCollection();
        $this->relatedTo = new ArrayCollection();
        $this->relatedProducts = new ArrayCollection();
        $this->crossSells = new ArrayCollection();
        $this->upSells = new ArrayCollection();
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Get price as a Money object
     */
    public function getPriceMoney(): Money
    {
        return Money::USD($this->price);
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
     * Get special price as a Money object
     */
    public function getSpecialPriceMoney(): ?Money
    {
        return $this->specialPrice !== null ? Money::USD($this->specialPrice) : null;
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
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        $this->categories->removeElement($category);
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
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getProduct() === $this) {
                $image->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductAttributeValue>
     */
    public function getAttributeValues(): Collection
    {
        return $this->attributeValues;
    }

    public function addAttributeValue(ProductAttributeValue $attributeValue): self
    {
        if (!$this->attributeValues->contains($attributeValue)) {
            $this->attributeValues->add($attributeValue);
            $attributeValue->setProduct($this);
        }

        return $this;
    }

    public function removeAttributeValue(ProductAttributeValue $attributeValue): self
    {
        if ($this->attributeValues->removeElement($attributeValue)) {
            // set the owning side to null (unless already changed)
            if ($attributeValue->getProduct() === $this) {
                $attributeValue->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    /**
     * @return Collection<int, ProductVariation>
     */
    public function getVariations(): Collection
    {
        return $this->variations;
    }

    public function addVariation(ProductVariation $variation): self
    {
        if (!$this->variations->contains($variation)) {
            $this->variations->add($variation);
            $variation->setParent($this);
        }

        return $this;
    }

    public function removeVariation(ProductVariation $variation): self
    {
        if ($this->variations->removeElement($variation)) {
            // set the owning side to null (unless already changed)
            if ($variation->getParent() === $this) {
                $variation->setParent(null);
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
            $inventory->setProduct($this);
        }

        return $this;
    }

    public function removeInventory(ProductInventory $inventory): self
    {
        if ($this->inventories->removeElement($inventory)) {
            // set the owning side to null (unless already changed)
            if ($inventory->getProduct() === $this) {
                $inventory->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ConfigurableOption>
     */
    public function getConfigurableOptions(): Collection
    {
        return $this->configurableOptions;
    }

    public function addConfigurableOption(ConfigurableOption $configurableOption): self
    {
        if (!$this->configurableOptions->contains($configurableOption)) {
            $this->configurableOptions->add($configurableOption);
            $configurableOption->setProduct($this);
        }

        return $this;
    }

    public function removeConfigurableOption(ConfigurableOption $configurableOption): self
    {
        if ($this->configurableOptions->removeElement($configurableOption)) {
            // set the owning side to null (unless already changed)
            if ($configurableOption->getProduct() === $this) {
                $configurableOption->setProduct(null);
            }
        }

        return $this;
    }

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

    /**
     * @return Collection<int, Product>
     */
    public function getRelatedTo(): Collection
    {
        return $this->relatedTo;
    }

    public function addRelatedTo(Product $product): self
    {
        if (!$this->relatedTo->contains($product)) {
            $this->relatedTo->add($product);
        }

        return $this;
    }

    public function removeRelatedTo(Product $product): self
    {
        $this->relatedTo->removeElement($product);
        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getRelatedProducts(): Collection
    {
        return $this->relatedProducts;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getCrossSells(): Collection
    {
        return $this->crossSells;
    }

    public function addCrossSell(Product $product): self
    {
        if (!$this->crossSells->contains($product)) {
            $this->crossSells->add($product);
        }

        return $this;
    }

    public function removeCrossSell(Product $product): self
    {
        $this->crossSells->removeElement($product);
        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getUpSells(): Collection
    {
        return $this->upSells;
    }

    public function addUpSell(Product $product): self
    {
        if (!$this->upSells->contains($product)) {
            $this->upSells->add($product);
        }

        return $this;
    }

    public function removeUpSell(Product $product): self
    {
        $this->upSells->removeElement($product);
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
     * Get current effective price (considers special price if applicable)
     */
    public function getCurrentPrice(): int
    {
        if ($this->hasValidSpecialPrice()) {
            return $this->specialPrice;
        }

        return $this->price;
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
