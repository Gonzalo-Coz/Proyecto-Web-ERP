<?php

declare(strict_types=1);

namespace App\Module\Security\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Cambio de contraseña desde el perfil: exige la contraseña actual. */
final class ChangePasswordPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'Ingresa tu contraseña actual.')]
        public readonly string $currentPassword,

        #[Assert\NotBlank(message: 'Ingresa la nueva contraseña.')]
        #[Assert\Length(min: 8, minMessage: 'La nueva contraseña debe tener al menos 8 caracteres.')]
        public readonly string $newPassword,
    ) {
    }
}
