<?php

declare(strict_types=1);

namespace App\Module\Maintenance\Controller;

use App\Module\Maintenance\Service\MaintenancePlanService;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Planes de mantenimiento por kilometraje (taller). Solo lectura: alimenta la
 * pantalla que arma la "venta de servicio". La creación de la venta usa el
 * módulo de Ventas existente (no se duplica aquí).
 */
#[Route('/api/v1/maintenance-plans')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class MaintenancePlanController
{
    public function __construct(private readonly MaintenancePlanService $service)
    {
    }

    #[Route('', name: 'maintenance_plans_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->service->listModels());
    }

    #[Route('/{id<\d+>}/service', name: 'maintenance_plans_service', methods: ['GET'])]
    public function service(int $id, Request $request): JsonResponse
    {
        return new JsonResponse($this->service->getService($id, (int) $request->query->get('km', 0)));
    }
}
