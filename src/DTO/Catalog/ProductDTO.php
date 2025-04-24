<?php

namespace App\DTO\Catalog;

use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Product;
class ProductDTO
{
    #[Assert\NotBlank]
    public string $name;

    #[Assert\NotBlank]
    public string $sku;

    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(0)]
    public int $price;

    public ?int $specialPrice = null;

    public ?bool $active = true;

    public array $categories = [];

    public array $tags = [];

    public string $type = Product::TYPE_SIMPLE;

}