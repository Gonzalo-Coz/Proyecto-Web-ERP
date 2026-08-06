<?php

declare(strict_types=1);

namespace App\Module\Inventory\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SparePartPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El código interno es obligatorio.')]
        #[Assert\Length(min: 2, max: 20)]
        public readonly string $internalCode,

        #[Assert\NotBlank(message: 'El código de repuesto es obligatorio.')]
        #[Assert\Length(min: 2, max: 40)]
        public readonly string $partCode,

        #[Assert\NotBlank(message: 'La descripción es obligatoria.')]
        #[Assert\Length(min: 3, max: 200)]
        public readonly string $description,

        #[Assert\Length(max: 50)]
        public readonly ?string $barcode = null,

        #[Assert\Positive]
        public readonly ?int $brandId = null,

        #[Assert\Positive]
        public readonly ?int $categoryId = null,

        #[Assert\Length(max: 20)]
        public readonly string $unitOfMeasure = 'UNIDAD',

        /** @var list<int> IDs de modelos compatibles. */
        #[Assert\All([new Assert\Type('integer')])]
        public readonly array $compatibleModelIds = [],

        #[Assert\PositiveOrZero]
        public readonly int $minStock = 0,

        #[Assert\PositiveOrZero]
        public readonly ?int $maxStock = null,

        #[Assert\PositiveOrZero]
        public readonly ?float $purchasePrice = null,

        #[Assert\PositiveOrZero]
        public readonly ?float $salePrice = null,

        #[Assert\Length(max: 100)]
        public readonly ?string $location = null,

        public readonly bool $isActive = true,

        /** Motivo del cambio de precio de venta (Adición A3); no se persiste en la entidad. */
        #[Assert\Length(max: 255)]
        public readonly ?string $priceChangeReason = null,
    ) {
    }
}
