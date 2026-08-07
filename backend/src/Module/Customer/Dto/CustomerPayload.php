<?php

declare(strict_types=1);

namespace App\Module\Customer\Dto;

use App\Module\Customer\Entity\Customer;
use Symfony\Component\Validator\Constraints as Assert;

final class CustomerPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El tipo de documento es obligatorio.')]
        #[Assert\Choice(choices: Customer::DOCUMENT_TYPES, message: 'Tipo de documento inválido.')]
        public readonly string $documentType,

        #[Assert\NotBlank(message: 'El número de documento es obligatorio.')]
        #[Assert\Length(max: 20)]
        public readonly string $documentNumber,

        #[Assert\NotBlank(message: 'El nombre o razón social es obligatorio.')]
        #[Assert\Length(min: 3, max: 200)]
        public readonly string $name,

        #[Assert\Length(max: 150)]
        public readonly ?string $tradeName = null,

        #[Assert\Length(max: 200)]
        public readonly ?string $address = null,

        #[Assert\Length(max: 100)]
        public readonly ?string $district = null,

        #[Assert\Length(max: 100)]
        public readonly ?string $province = null,

        #[Assert\Length(max: 100)]
        public readonly ?string $department = null,

        #[Assert\Length(max: 20)]
        public readonly ?string $phone = null,

        #[Assert\Length(max: 20)]
        public readonly ?string $mobile = null,

        #[Assert\Email(message: 'Correo electrónico inválido.')]
        #[Assert\Length(max: 150)]
        public readonly ?string $email = null,

        public readonly bool $isActive = true,

        /** Lista de precios asignada (Adición A4); null = predeterminada / precio base. */
        #[Assert\Positive]
        public readonly ?int $priceListId = null,

        /** Tipo de cliente administrable; define el % de descuento por defecto. */
        #[Assert\Positive]
        public readonly ?int $customerTypeId = null,
    ) {
    }
}
