<?php

declare(strict_types=1);

namespace App\Module\Security\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload de creación/edición de usuarios.
 * En edición, password es opcional (null = no cambiar).
 */
final class UserPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El nombre de usuario es obligatorio.')]
        #[Assert\Regex(pattern: '/^[a-z0-9._-]{3,50}$/i', message: 'Entre 3 y 50 caracteres alfanuméricos (se admiten . _ -).')]
        public readonly string $username,

        #[Assert\NotBlank(message: 'El correo es obligatorio.')]
        #[Assert\Email(message: 'Correo electrónico inválido.')]
        #[Assert\Length(max: 150)]
        public readonly string $email,

        #[Assert\NotBlank(message: 'El nombre completo es obligatorio.')]
        #[Assert\Length(min: 3, max: 150)]
        public readonly string $fullName,

        #[Assert\Length(min: 8, minMessage: 'La contraseña debe tener al menos 8 caracteres.')]
        public readonly ?string $password = null,

        /** @var list<int> */
        #[Assert\All([new Assert\Type('integer')])]
        public readonly array $roleIds = [],

        public readonly bool $isActive = true,
    ) {
    }
}
