<?php

declare(strict_types=1);

namespace App\Module\Security\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Datos editables del propio perfil (autoservicio).
 *
 * El nombre de usuario NO se incluye: es el identificador de acceso y la
 * clave de auditoría del sistema, por lo que permanece inmutable (coherente
 * con UserService::update). Cambiarlo requiere decisión del propietario.
 */
final class ProfilePayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El nombre completo es obligatorio.')]
        #[Assert\Length(min: 3, max: 150)]
        public readonly string $fullName,

        #[Assert\NotBlank(message: 'El correo es obligatorio.')]
        #[Assert\Email(message: 'Correo electrónico inválido.')]
        #[Assert\Length(max: 150)]
        public readonly string $email,

        #[Assert\Length(max: 30)]
        #[Assert\Regex(pattern: '/^[0-9+()\-\s]*$/', message: 'Teléfono inválido.')]
        public readonly ?string $phone = null,
    ) {
    }
}
