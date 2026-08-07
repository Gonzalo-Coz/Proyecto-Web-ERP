<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Dto;

use App\Module\Purchasing\Entity\Purchase;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload de compra. Las líneas (items) llegan como arreglos y las valida
 * PurchaseService línea por línea, con mensajes precisos por posición.
 */
final class PurchasePayload
{
    /**
     * @param list<array{itemType?: string, sparePartId?: int|null, motorcycleUnitId?: int|null,
     *                    quantity?: int, unitPrice?: float, discount?: float}> $items
     */
    public function __construct(
        #[Assert\NotBlank(message: 'El proveedor es obligatorio.')]
        #[Assert\Positive]
        public readonly int $supplierId,

        #[Assert\NotBlank(message: 'La fecha es obligatoria.')]
        #[Assert\Date(message: 'Fecha inválida (YYYY-MM-DD).')]
        public readonly string $purchaseDate,

        #[Assert\Choice(choices: Purchase::DOCUMENT_TYPES, message: 'Tipo de documento inválido.')]
        public readonly string $documentType,

        #[Assert\Count(min: 1, minMessage: 'La compra debe tener al menos una línea.')]
        public readonly array $items,

        /** Moneda del comprobante (PEN o USD). */
        #[Assert\Choice(choices: ['PEN', 'USD'])]
        public readonly string $currency = 'PEN',

        #[Assert\Length(max: 10)]
        public readonly ?string $series = null,

        #[Assert\Length(max: 20)]
        public readonly ?string $documentNumber = null,

        #[Assert\Positive]
        public readonly ?int $paymentMethodId = null,

        public readonly ?string $notes = null,
    ) {
    }
}
