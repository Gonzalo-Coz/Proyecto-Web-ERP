<?php

declare(strict_types=1);

namespace App\Module\Dashboard\Controller;

use App\Module\Dashboard\Service\DashboardService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/dashboard')]
#[OA\Tag(name: 'Dashboard')]
final class DashboardController
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    #[Route('', name: 'dashboard_summary', methods: ['GET'])]
    #[IsGranted('dashboard.main.view')]
    public function summary(): JsonResponse
    {
        return new JsonResponse($this->dashboardService->summary());
    }
}
