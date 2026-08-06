<?php

declare(strict_types=1);

namespace App\Shared\Pricing\Controller;

use App\Shared\Pricing\Service\PriceHistoryService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reporte de consulta del historial de precios (Adición A3 · §24.4).
 * Controller fino: delega en PriceHistoryService.
 */
#[Route('/api/v1/pricing/price-history')]
#[OA\Tag(name: 'Precios — Historial')]
final class PriceHistoryController
{
    public function __construct(private readonly PriceHistoryService $priceHistoryService)
    {
    }

    #[Route('', name: 'pricing_history_list', methods: ['GET'])]
    #[IsGranted('pricing.history.view')]
    public function list(Request $request): JsonResponse
    {
        $subjectId = $request->query->has('subjectId') ? $request->query->getInt('subjectId') : null;

        return new JsonResponse($this->priceHistoryService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            subjectType: trim($request->query->getString('subjectType', '')),
            subjectId: $subjectId,
            search: trim($request->query->getString('search', '')),
            from: $request->query->getString('from', ''),
            to: $request->query->getString('to', ''),
        ));
    }
}
