<?php

declare(strict_types=1);

namespace App\Module\Inventory\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Ajuste manual de stock: cantidad con signo (+entra / -sale) y motivo obligatorio. */
final class AdjustmentPayload
{
    public function __construct(
        #[Assert\NotEqualTo(0, message: 'La cantidad no puede ser cero.')]
        public readonly int $quantity,

        #[Assert\NotBlank(message: 'El motivo del ajuste es obligatorio.')]
        #[Assert\Length(min: 5, max: 200)]
        public readonly string $reason,
    ) {
    }
}
