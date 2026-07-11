<?php

declare(strict_types=1);

namespace App\Module\Supplier\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SupplierPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El RUC es obligatorio.')]
        #[Assert\Regex(pattern: '/^(10|15|17|20)\d{9}$/', message: 'El RUC debe tener 11 dígitos y un prefijo válido (10, 15, 17 o 20).')]
        public readonly string $ruc,

        #[Assert\NotBlank(message: 'La razón social es obligatoria.')]
        #[Assert\Length(min: 3, max: 200)]
        public readonly string $businessName,

        #[Assert\Length(max: 150)]
        public readonly ?string $tradeName = null,

        #[Assert\Length(max: 200)]
        public readonly ?string $address = null,

        #[Assert\Length(max: 100)]
        public readonly ?string $city = null,

        #[Assert\Length(max: 20)]
        public readonly ?string $phone = null,

        #[Assert\Email(message: 'Correo electrónico inválido.')]
        #[Assert\Length(max: 150)]
        public readonly ?string $email = null,

        #[Assert\Length(max: 150)]
        public readonly ?string $contactPerson = null,

        public readonly bool $isActive = true,
    ) {
    }
}
