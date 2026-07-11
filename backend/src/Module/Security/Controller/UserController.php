<?php

declare(strict_types=1);

namespace App\Module\Security\Controller;

use App\Module\Security\Dto\UserPayload;
use App\Module\Security\Entity\User;
use App\Module\Security\Service\UserService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administración de usuarios. Controller fino (§23.2): delega en UserService.
 * Autorización por matriz dinámica de permisos (§23.9) vía PermissionVoter.
 */
#[Route('/api/v1/security/users')]
#[OA\Tag(name: 'Seguridad — Usuarios')]
final class UserController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[Route('', name: 'security_users_list', methods: ['GET'])]
    #[IsGranted('security.users.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->userService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'username'),
            direction: $request->query->getString('direction', 'asc'),
        ));
    }

    #[Route('/{id<\d+>}', name: 'security_users_get', methods: ['GET'])]
    #[IsGranted('security.users.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->userService->get($id));
    }

    #[Route('', name: 'security_users_create', methods: ['POST'])]
    #[IsGranted('security.users.create')]
    public function create(#[MapRequestPayload] UserPayload $payload): JsonResponse
    {
        return new JsonResponse($this->userService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'security_users_update', methods: ['PUT'])]
    #[IsGranted('security.users.edit')]
    public function update(int $id, #[MapRequestPayload] UserPayload $payload): JsonResponse
    {
        return new JsonResponse($this->userService->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'security_users_delete', methods: ['DELETE'])]
    #[IsGranted('security.users.delete')]
    public function delete(int $id, #[CurrentUser] User $current): JsonResponse
    {
        $this->userService->delete($id, $current->getUsername());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
