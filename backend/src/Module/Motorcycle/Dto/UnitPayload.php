<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UnitPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El VIN es obligatorio.')]
        #[Assert\Regex(pattern: '/^[A-HJ-NPR-Za-hj-npr-z0-9]{17}$/', message: 'El VIN debe tener 17 caracteres alfanuméricos (sin I, O, Q).')]
        public readonly string $vin,

        #[Assert\NotBlank(message: 'El modelo es obligatorio.')]
        #[Assert\Positive]
        public readonly int $modelId,

        #[Assert\NotBlank(message: 'El color es obligatorio.')]
        #[Assert\Length(max: 50)]
        public readonly string $color,

        /**
         * Código interno correlativo. Vacío al crear: el sistema asigna el
         * siguiente (M-00001, M-00002, …). En edición se conserva el existente.
         */
        #[Assert\Length(max: 20)]
        public readonly ?string $internalCode = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $engineNumber = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $chassisNumber = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $series = null,

        #[Assert\Range(notInRangeMessage: 'Año de fabricación inválido.', min: 1990, max: 2100)]
        public readonly ?int $manufactureYear = null,

        /** Formato YYYY-MM-DD. */
        public readonly ?string $entryDate = null,

        public readonly ?string $purchaseDate = null,

        #[Assert\Positive]
        public readonly ?int $supplierId = null,

        #[Assert\PositiveOrZero]
        public readonly ?float $purchasePrice = null,

        #[Assert\PositiveOrZero]
        public readonly ?float $salePrice = null,

        #[Assert\Length(max: 100)]
        public readonly ?string $location = null,

        public readonly ?string $notes = null,

        /** Datos de importación (comprobante de vehículos importados). */
        #[Assert\Length(max: 40)]
        public readonly ?string $duaNumber = null,

        #[Assert\Length(max: 10)]
        public readonly ?string $duaItem = null,
    ) {
    }
}
