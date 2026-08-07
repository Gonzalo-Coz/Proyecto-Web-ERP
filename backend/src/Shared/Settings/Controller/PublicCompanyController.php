<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Shared\Settings\Service\SettingsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Datos públicos de identidad de la empresa (nombre y logos) para pantallas
 * NO autenticadas como el login y la cabecera. No expone información sensible.
 */
#[Route('/api/v1/public')]
#[OA\Tag(name: 'Público')]
final class PublicCompanyController
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    #[Route('/company', name: 'public_company', methods: ['GET'])]
    public function company(): JsonResponse
    {
        $s = $this->settingsService->all();

        return new JsonResponse([
            'name' => $s['company.name'] ?? '',
            'tradeName' => $s['company.trade_name'] ?? '',
            'logoFullPath' => $s['company.logo_full_path'] ?? '',
            'logoIconPath' => $s['company.logo_icon_path'] ?? '',
        ]);
    }
}
