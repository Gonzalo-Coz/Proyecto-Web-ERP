<?php

declare(strict_types=1);

namespace App\Module\Customer\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CustomerTypePayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El nombre del tipo es obligatorio.')]
        #[Assert\Length(min: 2, max: 60)]
        public readonly string $name,

        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'El descuento debe estar entre 0 y 100%.')]
        public readonly float $discountPercent = 0.0,

        public readonly bool $isActive = true,
    ) {
    }
}
