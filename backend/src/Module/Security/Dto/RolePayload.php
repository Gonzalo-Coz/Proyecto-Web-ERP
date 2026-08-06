<?php

declare(strict_types=1);

namespace App\Module\Security\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RolePayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El código del rol es obligatorio.')]
        #[Assert\Regex(pattern: '/^[A-Z0-9_]{2,50}$/i', message: 'Entre 2 y 50 caracteres alfanuméricos o guion bajo.')]
        public readonly string $code,

        #[Assert\NotBlank(message: 'El nombre del rol es obligatorio.')]
        #[Assert\Length(min: 3, max: 100)]
        public readonly string $name,

        public readonly ?string $description = null,

        /** @var list<string> Códigos de permiso "modulo.pantalla.accion". */
        #[Assert\All([new Assert\Type('string')])]
        public readonly array $permissionCodes = [],

        public readonly bool $isActive = true,

        /** Límite de descuento (%) del rol; null = sin límite (Adición A2). */
        #[Assert\Range(notInRangeMessage: 'El límite de descuento debe estar entre 0 y 100.', min: 0, max: 100)]
        public readonly ?float $maxDiscountPercent = null,
    ) {
    }
}
