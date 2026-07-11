<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ModelPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'La marca es obligatoria.')]
        #[Assert\Positive]
        public readonly int $brandId,

        #[Assert\NotBlank(message: 'El modelo es obligatorio.')]
        #[Assert\Length(min: 2, max: 100)]
        public readonly string $model,

        #[Assert\Range(notInRangeMessage: 'Año modelo inválido.', min: 1990, max: 2100)]
        public readonly int $modelYear,

        #[Assert\Length(max: 100)]
        public readonly ?string $version = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $engineCapacity = null,

        #[Assert\Length(max: 100)]
        public readonly ?string $engineType = null,

        #[Assert\Length(max: 50)]
        public readonly ?string $power = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $fuelType = null,

        #[Assert\Length(max: 50)]
        public readonly ?string $transmission = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $tankCapacity = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $weight = null,

        #[Assert\Length(max: 200)]
        public readonly ?string $colors = null,

        #[Assert\PositiveOrZero]
        public readonly ?int $warrantyMonths = null,

        #[Assert\PositiveOrZero]
        public readonly ?float $referencePrice = null,

        public readonly bool $isActive = true,
    ) {
    }
}
