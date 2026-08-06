<?php

declare(strict_types=1);

namespace App\Module\Promotion\Controller;

use App\Module\Promotion\Dto\PromotionPayload;
use App\Module\Promotion\Service\PromotionResolver;
use App\Module\Promotion\Service\PromotionService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Promociones (Adición A5), submódulo de Ventas. Controller fino.
 */
#[Route('/api/v1/promotions')]
#[OA\Tag(name: 'Ventas — Promociones')]
final class PromotionController
{
    public function __construct(
        private readonly PromotionService $promotionService,
        private readonly PromotionResolver $promotionResolver,
    ) {
    }

    #[Route('', name: 'promotions_list', methods: ['GET'])]
    #[IsGranted('sales.promotions.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->promotionService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'startDate'),
            direction: $request->query->getString('direction', 'desc'),
        ));
    }

    #[Route('/{id<\d+>}', name: 'promotions_get', methods: ['GET'])]
    #[IsGranted('sales.promotions.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->promotionService->get($id));
    }

    #[Route('', name: 'promotions_create', methods: ['POST'])]
    #[IsGranted('sales.promotions.create')]
    public function create(#[MapRequestPayload] PromotionPayload $payload): JsonResponse
    {
        return new JsonResponse($this->promotionService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'promotions_update', methods: ['PUT'])]
    #[IsGranted('sales.promotions.edit')]
    public function update(int $id, #[MapRequestPayload] PromotionPayload $payload): JsonResponse
    {
        return new JsonResponse($this->promotionService->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'promotions_delete', methods: ['DELETE'])]
    #[IsGranted('sales.promotions.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->promotionService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Promociones aplicables a un producto en una fecha (para prellenado en Ventas).
     * Cualquier usuario que pueda vender puede consultarlo.
     */
    #[Route('/applicable', name: 'promotions_applicable', methods: ['GET'])]
    public function applicable(Request $request): JsonResponse
    {
        $dateStr = $request->query->getString('date', date('Y-m-d'));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) === 1
            ? new \DateTimeImmutable($dateStr)
            : new \DateTimeImmutable('today');

        return new JsonResponse($this->promotionResolver->applicable(
            trim($request->query->getString('subjectType', '')),
            $request->query->getInt('subjectId'),
            $date,
        ));
    }
}
