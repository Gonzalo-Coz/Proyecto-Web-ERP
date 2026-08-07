<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Shared\Media\ImagePreset;
use App\Shared\Media\ImageStorageService;
use App\Shared\Settings\Service\SettingsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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

    /** Sube/reemplaza un logo de la empresa. kind = full (login) | icon (cabecera/pestaña). */
    #[Route('/logo', name: 'settings_logo_upload', methods: ['POST'])]
    #[IsGranted('settings.general.edit')]
    public function uploadLogo(Request $request, ImageStorageService $storage): JsonResponse
    {
        $file = $request->files->get('logo');
        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('No se recibió ninguna imagen.');
        }

        $key = $request->request->get('kind') === 'icon' ? 'company.logo_icon_path' : 'company.logo_full_path';
        $previous = $this->settingsService->get($key);
        $path = $storage->store($file, ImagePreset::LOGO);
        $this->settingsService->update([$key => $path]);
        $storage->delete($previous);

        return new JsonResponse(['key' => $key, 'path' => $path]);
    }

    /** Elimina un logo y vuelve al predeterminado. */
    #[Route('/logo', name: 'settings_logo_remove', methods: ['DELETE'])]
    #[IsGranted('settings.general.edit')]
    public function removeLogo(Request $request, ImageStorageService $storage): JsonResponse
    {
        $key = $request->query->get('kind') === 'icon' ? 'company.logo_icon_path' : 'company.logo_full_path';
        $previous = $this->settingsService->get($key);
        $this->settingsService->update([$key => '']);
        $storage->delete($previous);

        return new JsonResponse(['key' => $key, 'path' => '']);
    }
}
