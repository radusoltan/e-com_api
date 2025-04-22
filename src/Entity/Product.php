<?php

namespace App\Entity;

use App\Entity\Trait\ProductAvailabilityTrait;
use App\Entity\Trait\ProductBasicTrait;
use App\Entity\Trait\ProductPricingTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use Money\Money;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{

    use ProductBasicTrait;
    use ProductPricingTrait;
    use ProductAvailabilityTrait;
    use TimestampableTrait;

    // Product types
    const TYPE_SIMPLE = 'simple';
    const TYPE_CONFIGURABLE = 'configurable';
    const TYPE_VIRTUAL = 'virtual';
    const TYPE_DOWNLOADABLE = 'downloadable';
    const TYPE_BUNDLE = 'bundle';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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

        // Initialize parent constructors
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * @return Collection<int, Image>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Image $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(Image $image): self
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
     * Get default image or first image
     */
    public function getDefaultImage(): ?Image
    {
        foreach ($this->images as $image) {
            if ($image->isDefault()) {
                return $image;
            }
        }

        return $this->images->first() ?: null;
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
     * Get attribute value by attribute code
     */
    public function getAttributeValue(string $attributeCode): ?ProductAttributeValue
    {
        foreach ($this->attributeValues as $attributeValue) {
            if ($attributeValue->getAttribute()->getCode() === $attributeCode) {
                return $attributeValue;
            }
        }

        return null;
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
     * Get price as a Money object
     */
    public function getPriceMoney(): Money
    {
        return new Money(
            $this->price,
            new Currency($this->currencyCode ?? 'USD')
        );
    }

    /**
     * Get special price as a Money object
     */
    public function getSpecialPriceMoney(): ?Money
    {
        return $this->specialPrice !== null
            ? new Money($this->specialPrice, new Currency($this->currencyCode ?? 'USD'))
            : null;
    }

    /**
     * Get current price as a Money object
     */
    public function getCurrentPriceMoney(): Money
    {
        return new Money(
            $this->getCurrentPrice(),
            new Currency($this->currencyCode ?? 'USD')
        );
    }

    /**
     * Check if product can be purchased
     * NOTE: In a real implementation, this should delegate to an inventory service
     */
    public function canBePurchased(): bool
    {
        return $this->isActive() && $this->isAvailable();
    }

    /**
     * Get total stock quantity across all warehouses
     */
    public function getTotalStockQuantity(): int
    {
        $total = 0;
        foreach ($this->inventories as $inventory) {
            $total += $inventory->getQuantity();
        }
        return $total;
    }

    /**
     * Get total available stock quantity (not reserved)
     */
    public function getTotalAvailableQuantity(): int
    {
        $total = 0;
        foreach ($this->inventories as $inventory) {
            $total += $inventory->getAvailableQuantity();
        }
        return $total;
    }

    /**
     * Check if product has any stock
     */
    public function hasStock(): bool
    {
        return $this->getTotalAvailableQuantity() > 0;
    }

    /**
     * @ORM\PreUpdate
     */
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
