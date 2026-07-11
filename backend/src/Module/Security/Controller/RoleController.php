<?php

declare(strict_types=1);

namespace App\Module\Security\Controller;

use App\Module\Security\Dto\RolePayload;
use App\Module\Security\Service\RoleService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/security/roles')]
#[OA\Tag(name: 'Seguridad — Roles')]
final class RoleController
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    #[Route('', name: 'security_roles_list', methods: ['GET'])]
    #[IsGranted('security.roles.view')]
    public function list(): JsonResponse
    {
        return new JsonResponse(['data' => $this->roleService->list()]);
    }

    #[Route('/{id<\d+>}', name: 'security_roles_get', methods: ['GET'])]
    #[IsGranted('security.roles.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->roleService->get($id));
    }

    #[Route('', name: 'security_roles_create', methods: ['POST'])]
    #[IsGranted('security.roles.create')]
    public function create(#[MapRequestPayload] RolePayload $payload): JsonResponse
    {
        return new JsonResponse($this->roleService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'security_roles_update', methods: ['PUT'])]
    #[IsGranted('security.roles.edit')]
    public function update(int $id, #[MapRequestPayload] RolePayload $payload): JsonResponse
    {
        return new JsonResponse($this->roleService->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'security_roles_delete', methods: ['DELETE'])]
    #[IsGranted('security.roles.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->roleService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
