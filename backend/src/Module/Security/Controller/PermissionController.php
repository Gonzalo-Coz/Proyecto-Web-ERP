<?php

declare(strict_types=1);

namespace App\Module\Security\Controller;

use App\Module\Security\Repository\PermissionRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/security/permissions')]
#[OA\Tag(name: 'Seguridad — Permisos')]
final class PermissionController
{
    public function __construct(private readonly PermissionRepository $permissionRepository)
    {
    }

    /** Catálogo completo agrupado por módulo, para la matriz de roles. */
    #[Route('', name: 'security_permissions_list', methods: ['GET'])]
    #[IsGranted('security.roles.view')]
    public function list(): JsonResponse
    {
        $grouped = [];
        foreach ($this->permissionRepository->findBy([], ['code' => 'ASC']) as $permission) {
            $grouped[$permission->getModule()][] = [
                'code' => $permission->getCode(),
                'screen' => $permission->getScreen(),
                'action' => $permission->getAction(),
                'name' => $permission->getName(),
            ];
        }

        return new JsonResponse(['data' => $grouped]);
    }
}
