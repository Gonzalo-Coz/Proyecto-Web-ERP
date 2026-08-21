<?php

declare(strict_types=1);

namespace App\Module\Sales\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload de venta/cotización. Las líneas llegan como arreglos y
 * SaleService las valida una a una (mismo patrón que Compras).
 */
final class SalePayload
{
    /**
     * @param list<array{itemType?: string, sparePartId?: int|null, motorcycleUnitId?: int|null,
     *                    description?: string|null, quantity?: int, unitPrice?: float,
     *                    discountPercent?: float, discountAmount?: float}> $items
     */
    public function __construct(
        #[Assert\NotBlank(message: 'El cliente es obligatorio.')]
        #[Assert\Positive]
        public readonly int $customerId,

        #[Assert\NotBlank(message: 'La fecha es obligatoria.')]
        #[Assert\Date(message: 'Fecha inválida (YYYY-MM-DD).')]
        public readonly string $saleDate,

        #[Assert\Count(min: 1, minMessage: 'La venta debe tener al menos una línea.')]
        public readonly array $items,

        /** true = venta directa (se completa de inmediato); false = cotización. */
        public readonly bool $complete = false,

        /** true = IGV incluido en el precio (zona local); false = IGV agregado (exterior). */
        public readonly bool $igvIncluded = true,

        /** true = operación exonerada de IGV (Amazonía, Ley 27037): no se aplica IGV. */
        public readonly bool $igvExempt = false,

        /** Moneda de la venta: PEN o USD (no se convierte). */
        #[Assert\Choice(choices: ['PEN', 'USD'])]
        public readonly string $currency = 'PEN',

        public readonly ?string $notes = null,
    ) {
    }
}
