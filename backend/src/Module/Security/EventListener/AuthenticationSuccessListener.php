<?php

declare(strict_types=1);

namespace App\Module\Security\EventListener;

use App\Module\Security\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Añade el objeto "user" a la respuesta del login para que el frontend
 * reciba en una sola llamada el token, los roles y los permisos.
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class AuthenticationSuccessListener
{
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $data['user'] = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'fullName' => $user->getFullName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'permissions' => $user->getPermissionCodes(),
        ];

        $event->setData($data);
    }
}
