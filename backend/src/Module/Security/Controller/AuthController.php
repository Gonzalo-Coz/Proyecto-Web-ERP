<?php

declare(strict_types=1);

namespace App\Module\Security\Controller;

use App\Module\Security\Entity\User;
use App\Shared\Media\ImageStorageService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/auth')]
#[OA\Tag(name: 'Autenticación')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly ImageStorageService $imageStorage)
    {
    }

    /**
     * Interceptado por el firewall json_login: nunca se ejecuta.
     * Existe para que la ruta esté registrada y documentada en OpenAPI.
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Iniciar sesión',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(
            required: ['username', 'password'],
            properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Token JWT y datos del usuario'),
            new OA\Response(response: 401, description: 'Credenciales inválidas'),
        ],
    )]
    public function login(): never
    {
        throw new \LogicException('Esta ruta la intercepta el firewall json_login.');
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    #[OA\Get(
        summary: 'Usuario autenticado actual',
        responses: [new OA\Response(response: 200, description: 'Datos y permisos del usuario')],
    )]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'fullName' => $user->getFullName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'avatarUrl' => $this->imageStorage->publicUrl($user->getAvatarPath()),
            'roles' => $user->getRoles(),
            'permissions' => $user->getPermissionCodes(),
        ]);
    }
}
