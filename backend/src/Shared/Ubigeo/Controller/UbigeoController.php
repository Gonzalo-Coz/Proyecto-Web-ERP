<?php

declare(strict_types=1);

namespace App\Shared\Ubigeo\Controller;

use App\Shared\Ubigeo\Service\UbigeoService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ubicaciones del Perú en cascada (departamento → provincia → distrito).
 * Datos de referencia; disponible para cualquier usuario autenticado.
 */
#[Route('/api/v1/ubigeo')]
#[OA\Tag(name: 'Ubigeo')]
final class UbigeoController
{
    public function __construct(private readonly UbigeoService $ubigeo)
    {
    }

    #[Route('/departments', name: 'ubigeo_departments', methods: ['GET'])]
    public function departments(): JsonResponse
    {
        return new JsonResponse($this->ubigeo->departments());
    }

    #[Route('/provinces/{departmentId<\d+>}', name: 'ubigeo_provinces', methods: ['GET'])]
    public function provinces(string $departmentId): JsonResponse
    {
        return new JsonResponse($this->ubigeo->provinces($departmentId));
    }

    #[Route('/districts/{provinceId<\d+>}', name: 'ubigeo_districts', methods: ['GET'])]
    public function districts(string $provinceId): JsonResponse
    {
        return new JsonResponse($this->ubigeo->districts($provinceId));
    }
}
