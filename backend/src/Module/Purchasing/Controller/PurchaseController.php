<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Controller;

use App\Module\Purchasing\Dto\PurchasePayload;
use App\Module\Purchasing\Service\PurchaseService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/purchases')]
#[OA\Tag(name: 'Compras')]
final class PurchaseController
{
    public function __construct(private readonly PurchaseService $purchaseService)
    {
    }

    #[Route('', name: 'purchases_list', methods: ['GET'])]
    #[IsGranted('purchases.list.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->purchaseService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'purchaseDate'),
            direction: $request->query->getString('direction', 'desc'),
        ));
    }

    #[Route('/{id<\d+>}', name: 'purchases_get', methods: ['GET'])]
    #[IsGranted('purchases.list.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->purchaseService->get($id));
    }

    #[Route('', name: 'purchases_create', methods: ['POST'])]
    #[IsGranted('purchases.list.create')]
    public function create(#[MapRequestPayload] PurchasePayload $payload): JsonResponse
    {
        return new JsonResponse($this->purchaseService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/cancel', name: 'purchases_cancel', methods: ['POST'])]
    #[IsGranted('purchases.list.cancel')]
    public function cancel(int $id): JsonResponse
    {
        return new JsonResponse($this->purchaseService->cancel($id));
    }
}
