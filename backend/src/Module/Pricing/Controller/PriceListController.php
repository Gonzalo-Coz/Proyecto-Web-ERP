<?php

declare(strict_types=1);

namespace App\Module\Pricing\Controller;

use App\Module\Pricing\Dto\PriceListPayload;
use App\Module\Pricing\Service\PriceListService;
use App\Module\Pricing\Service\PriceResolver;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Listas de precios y resolución de precio (Adición A4). Controller fino.
 */
#[Route('/api/v1/pricing')]
#[OA\Tag(name: 'Precios — Listas')]
final class PriceListController
{
    public function __construct(
        private readonly PriceListService $priceListService,
        private readonly PriceResolver $priceResolver,
    ) {
    }

    #[Route('/price-lists', name: 'pricing_lists_list', methods: ['GET'])]
    #[IsGranted('pricing.price_lists.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->priceListService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'name'),
            direction: $request->query->getString('direction', 'asc'),
        ));
    }

    #[Route('/price-lists/{id<\d+>}', name: 'pricing_lists_get', methods: ['GET'])]
    #[IsGranted('pricing.price_lists.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->priceListService->get($id));
    }

    #[Route('/price-lists', name: 'pricing_lists_create', methods: ['POST'])]
    #[IsGranted('pricing.price_lists.create')]
    public function create(#[MapRequestPayload] PriceListPayload $payload): JsonResponse
    {
        return new JsonResponse($this->priceListService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/price-lists/{id<\d+>}', name: 'pricing_lists_update', methods: ['PUT'])]
    #[IsGranted('pricing.price_lists.edit')]
    public function update(int $id, #[MapRequestPayload] PriceListPayload $payload): JsonResponse
    {
        return new JsonResponse($this->priceListService->update($id, $payload));
    }

    #[Route('/price-lists/{id<\d+>}', name: 'pricing_lists_delete', methods: ['DELETE'])]
    #[IsGranted('pricing.price_lists.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->priceListService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Resolución de precio para Ventas (prellenado). Cualquier usuario
     * autenticado que pueda vender puede consultarlo; no expone la lista, solo el precio.
     */
    #[Route('/resolve', name: 'pricing_resolve', methods: ['GET'])]
    public function resolve(Request $request): JsonResponse
    {
        $customerId = $request->query->has('customerId') ? $request->query->getInt('customerId') : null;

        return new JsonResponse($this->priceResolver->resolve(
            $customerId,
            trim($request->query->getString('subjectType', '')),
            $request->query->getInt('subjectId'),
        ));
    }
}
