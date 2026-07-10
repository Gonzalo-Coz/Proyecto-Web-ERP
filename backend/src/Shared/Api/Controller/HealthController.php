<?php

declare(strict_types=1);

namespace App\Shared\Api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint de verificación del estado de la API.
 * Permite comprobar que el backend está operativo tras la instalación.
 */
final class HealthController
{
    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'application' => 'YIGM ERP API',
            'version' => '0.1.0',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
