<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class ProductData
{
    public function __construct(
        public string $barcode,
        public string $name,
        public ?string $brand = null,
        public ?string $imageUrl = null,
        public ?string $nutriscore = null,
        public ?string $quantityLabel = null,
    ) {
    }
}
