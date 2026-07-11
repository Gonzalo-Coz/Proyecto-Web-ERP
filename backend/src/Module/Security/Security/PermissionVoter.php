<?php

declare(strict_types=1);

namespace App\Module\Security\Security;

use App\Module\Security\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter de la matriz de permisos dinámica (§23.9).
 *
 * Uso en cualquier controller o service:
 *   $this->denyAccessUnlessGranted('customers.list.view');
 *   #[IsGranted('sales.orders.create')]
 *
 * El atributo debe tener la forma "modulo.pantalla.accion".
 * Un rol superadministrador (comodín "*") concede todo.
 */
final class PermissionVoter extends Voter
{
    private const PATTERN = '/^[a-z0-9_]+\.[a-z0-9_]+\.[a-z0-9_]+$/';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return preg_match(self::PATTERN, $attribute) === 1;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $codes = $user->getPermissionCodes();

        return in_array('*', $codes, true) || in_array($attribute, $codes, true);
    }
}
