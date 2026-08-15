<?php

declare(strict_types=1);

namespace App\Module\Dispatch\Dto;

use App\Module\Dispatch\Entity\DispatchGuide;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Datos para crear una Guía de Remisión. Los ítems llegan como arreglo
 * ({descripcion, cantidad, unidad, codigo}) y se validan en el servicio.
 */
final class DispatchGuidePayload
{
    /**
     * @param list<array{descripcion?: string, cantidad?: float|int, unidad?: string, codigo?: string}> $items
     */
    public function __construct(
        /** Fecha de inicio del traslado (YYYY-MM-DD). */
        #[Assert\NotBlank(message: 'La fecha de traslado es obligatoria.')]
        #[Assert\Date]
        public readonly string $transferDate,

        #[Assert\Choice(callback: [DispatchGuide::class, 'motiveCodes'], message: 'Motivo de traslado inválido.')]
        public readonly string $motive,

        #[Assert\NotBlank(message: 'El destinatario es obligatorio.')]
        public readonly string $recipientDocType,

        #[Assert\NotBlank(message: 'El documento del destinatario es obligatorio.')]
        public readonly string $recipientDocNumber,

        #[Assert\NotBlank(message: 'El nombre del destinatario es obligatorio.')]
        #[Assert\Length(max: 200)]
        public readonly string $recipientName,

        #[Assert\NotBlank(message: 'La dirección de partida es obligatoria.')]
        #[Assert\Length(max: 200)]
        public readonly string $originAddress,

        #[Assert\NotBlank(message: 'La dirección de llegada es obligatoria.')]
        #[Assert\Length(max: 200)]
        public readonly string $destinationAddress,

        #[Assert\Count(min: 1, minMessage: 'La guía debe tener al menos un ítem.')]
        public readonly array $items,

        #[Assert\Length(max: 6)]
        public readonly ?string $originUbigeo = null,

        #[Assert\Length(max: 6)]
        public readonly ?string $destinationUbigeo = null,

        /** Modalidad: 01 = público, 02 = privado. */
        #[Assert\Choice(choices: ['01', '02'])]
        public readonly string $transportMode = '02',

        #[Assert\Length(max: 11)]
        public readonly ?string $carrierRuc = null,

        #[Assert\Length(max: 200)]
        public readonly ?string $carrierName = null,

        #[Assert\Length(max: 20)]
        public readonly ?string $vehiclePlate = null,

        #[Assert\Length(max: 20)]
        public readonly ?string $driverLicense = null,

        #[Assert\Length(max: 200)]
        public readonly ?string $driverName = null,

        #[Assert\PositiveOrZero]
        public readonly float $totalWeight = 0.0,

        #[Assert\Positive]
        public readonly int $packages = 1,

        #[Assert\Positive]
        public readonly ?int $saleId = null,

        public readonly ?string $observations = null,
    ) {
    }
}
