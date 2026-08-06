<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Shared\Settings\Service\SettingsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/settings')]
#[OA\Tag(name: 'Configuración General')]
final class SettingsController
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    #[Route('', name: 'settings_get', methods: ['GET'])]
    #[IsGranted('settings.general.view')]
    public function all(): JsonResponse
    {
        return new JsonResponse(['data' => $this->settingsService->all()]);
    }

    #[Route('', name: 'settings_update', methods: ['PUT'])]
    #[IsGranted('settings.general.edit')]
    public function update(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->settingsService->update($request->toArray())]);
    }
}
