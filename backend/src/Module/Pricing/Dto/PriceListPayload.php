<?php

declare(strict_types=1);

namespace App\Module\Pricing\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload de lista de precios (Adición A4). Las líneas llegan como arreglos
 * {subjectType, subjectId, price} y PriceListService las valida una a una.
 */
final class PriceListPayload
{
    /**
     * @param list<array{subjectType?: string, subjectId?: int, price?: float}> $items
     */
    public function __construct(
        #[Assert\NotBlank(message: 'El código es obligatorio.')]
        #[Assert\Regex(pattern: '/^[A-Z0-9._-]{2,30}$/i', message: 'Código inválido (2–30, alfanumérico).')]
        public readonly string $code,

        #[Assert\NotBlank(message: 'El nombre es obligatorio.')]
        #[Assert\Length(min: 3, max: 100)]
        public readonly string $name,

        public readonly bool $isDefault = false,

        public readonly bool $isActive = true,

        public readonly array $items = [],
    ) {
    }
}
