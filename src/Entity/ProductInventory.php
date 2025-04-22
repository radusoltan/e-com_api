<?php

namespace App\Entity;

use App\Repository\ProductInventoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductInventoryRepository::class)]
#[ORM\UniqueConstraint(
    name: 'unique_product_warehouse',
    columns: ['product_id', 'warehouse_id']
)]
#[ORM\UniqueConstraint(
    name: 'unique_variation_warehouse',
    columns: ['product_variation_id', 'warehouse_id']
)]
class ProductInventory
{
    const STATUS_IN_STOCK = 'in_stock';
    const STATUS_OUT_OF_STOCK = 'out_of_stock';
    const STATUS_BACKORDER = 'backorder';
    const STATUS_RESERVED = 'reserved';
    const STATUS_DISCONTINUED = 'discontinued';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'inventories')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'inventories')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ProductVariation $productVariation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Warehouse $warehouse = null;

    #[ORM\Column]
    private ?int $quantity = 0;

    #[ORM\Column]
    private ?int $reserved = 0;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_IN_STOCK;

    #[ORM\Column]
    private ?bool $backordersAllowed = false;

    #[ORM\Column(nullable: true)]
    private ?int $lowStockThreshold = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shelfLocation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $batchNumber = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiryDate = null;

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

    public function getProductVariation(): ?ProductVariation
    {
        return $this->productVariation;
    }

    public function setProductVariation(?ProductVariation $productVariation): self
    {
        $this->productVariation = $productVariation;
        return $this;
    }

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouse $warehouse): self
    {
        $this->warehouse = $warehouse;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        $this->updateStatus();
        return $this;
    }

    public function getReserved(): ?int
    {
        return $this->reserved;
    }

    public function setReserved(int $reserved): self
    {
        $this->reserved = $reserved;
        return $this;
    }

    public function getAvailableQuantity(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isBackordersAllowed(): ?bool
    {
        return $this->backordersAllowed;
    }

    public function setBackordersAllowed(bool $backordersAllowed): self
    {
        $this->backordersAllowed = $backordersAllowed;
        return $this;
    }

    public function getLowStockThreshold(): ?int
    {
        return $this->lowStockThreshold;
    }

    public function setLowStockThreshold(?int $lowStockThreshold): self
    {
        $this->lowStockThreshold = $lowStockThreshold;
        return $this;
    }

    public function getShelfLocation(): ?string
    {
        return $this->shelfLocation;
    }

    public function setShelfLocation(?string $shelfLocation): self
    {
        $this->shelfLocation = $shelfLocation;
        return $this;
    }

    public function getBatchNumber(): ?string
    {
        return $this->batchNumber;
    }

    public function setBatchNumber(?string $batchNumber): self
    {
        $this->batchNumber = $batchNumber;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeImmutable $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
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
     * Update status based on quantity
     */
    private function updateStatus(): void
    {
        if ($this->quantity <= 0) {
            $this->status = $this->backordersAllowed ? self::STATUS_BACKORDER : self::STATUS_OUT_OF_STOCK;
        } elseif ($this->quantity > 0 && $this->lowStockThreshold !== null && $this->quantity <= $this->lowStockThreshold) {
            $this->status = self::STATUS_IN_STOCK; // Still in stock but low
        } else {
            $this->status = self::STATUS_IN_STOCK;
        }
    }

    /**
     * Check if item is in stock or backorderable
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_IN_STOCK ||
            ($this->status === self::STATUS_BACKORDER && $this->backordersAllowed);
    }

    /**
     * Check if inventory is low stock
     */
    public function isLowStock(): bool
    {
        if ($this->lowStockThreshold === null) {
            return false;
        }

        return $this->quantity > 0 && $this->quantity <= $this->lowStockThreshold;
    }

    /**
     * Reserve a quantity of the product
     */
    public function reserve(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        // Can only reserve available quantity
        $availableToReserve = min($quantity, $this->getAvailableQuantity());

        if ($availableToReserve <= 0 && !$this->backordersAllowed) {
            return false;
        }

        $this->reserved += $availableToReserve;
        return true;
    }

    /**
     * Release a reserved quantity
     */
    public function releaseReservation(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $quantityToRelease = min($quantity, $this->reserved);
        $this->reserved -= $quantityToRelease;

        return true;
    }
}
