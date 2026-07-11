<?php

declare(strict_types=1);

namespace App\Module\Security\Security;

use App\Module\Security\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloquea el acceso de usuarios inactivos o eliminados lógicamente,
 * tanto en el login como en cada petición autenticada con JWT.
 */
final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive() || $user->isDeleted()) {
            throw new CustomUserMessageAccountStatusException('El usuario se encuentra inactivo.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
