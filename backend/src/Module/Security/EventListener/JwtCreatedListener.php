<?php

declare(strict_types=1);

namespace App\Module\Security\EventListener;

use App\Module\Security\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Enriquece el payload del token con datos mínimos del usuario.
 * Los permisos NO van en el token (pueden cambiar sin reemitirlo);
 * el frontend los obtiene del login y de /auth/me.
 */
#[AsEventListener(event: Events::JWT_CREATED)]
final class JwtCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['uid'] = $user->getId();
        $payload['fullName'] = $user->getFullName();

        $event->setData($payload);
    }
}
