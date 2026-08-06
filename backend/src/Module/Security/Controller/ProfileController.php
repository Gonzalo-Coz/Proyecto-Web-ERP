<?php

declare(strict_types=1);

namespace App\Module\Security\Controller;

use App\Module\Security\Dto\ChangePasswordPayload;
use App\Module\Security\Dto\ProfilePayload;
use App\Module\Security\Entity\User;
use App\Module\Security\Service\ProfileService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Autoservicio del perfil del usuario autenticado.
 * No requiere permiso de la matriz (§23.9): cada quien administra SU perfil.
 * El firewall ya exige autenticación para todo /api.
 */
#[Route('/api/v1/profile')]
#[OA\Tag(name: 'Perfil')]
final class ProfileController
{
    public function __construct(private readonly ProfileService $profileService)
    {
    }

    #[Route('', name: 'profile_get', methods: ['GET'])]
    #[OA\Get(summary: 'Perfil del usuario autenticado')]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->profileService->toArray($user));
    }

    #[Route('', name: 'profile_update', methods: ['PATCH'])]
    #[OA\Patch(summary: 'Actualizar datos del perfil')]
    public function update(#[CurrentUser] User $user, #[MapRequestPayload] ProfilePayload $payload): JsonResponse
    {
        return new JsonResponse($this->profileService->update($user, $payload));
    }

    #[Route('/password', name: 'profile_password', methods: ['PATCH'])]
    #[OA\Patch(summary: 'Cambiar la contraseña propia')]
    public function changePassword(#[CurrentUser] User $user, #[MapRequestPayload] ChangePasswordPayload $payload): JsonResponse
    {
        $this->profileService->changePassword($user, $payload);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/avatar', name: 'profile_avatar_set', methods: ['POST'])]
    #[OA\Post(summary: 'Subir/actualizar la fotografía de perfil')]
    public function setAvatar(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $file = $request->files->get('avatar');
        if ($file === null) {
            throw new UnprocessableEntityHttpException('No se recibió ninguna imagen.');
        }

        return new JsonResponse($this->profileService->setAvatar($user, $file));
    }

    #[Route('/avatar', name: 'profile_avatar_remove', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Eliminar la fotografía de perfil')]
    public function removeAvatar(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->profileService->removeAvatar($user));
    }
}
