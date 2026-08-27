<?php

declare(strict_types=1);

namespace App\Module\Sales\Controller;

use App\Module\Sales\Dto\SalePayload;
use App\Module\Sales\Service\SaleService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/sales')]
#[OA\Tag(name: 'Ventas')]
final class SaleController
{
    public function __construct(private readonly SaleService $saleService)
    {
    }

    #[Route('', name: 'sales_list', methods: ['GET'])]
    #[IsGranted('sales.list.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->saleService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'saleDate'),
            direction: $request->query->getString('direction', 'desc'),
            status: $request->query->getString('status', ''),
            customerId: $request->query->getInt('customerId', 0),
            pendingOnly: $request->query->getBoolean('pending', false),
        ));
    }

    #[Route('/customer-units/{customerId<\d+>}', name: 'sales_customer_units', methods: ['GET'])]
    #[IsGranted('sales.list.view')]
    public function customerUnits(int $customerId): JsonResponse
    {
        return new JsonResponse($this->saleService->customerUnitIds($customerId));
    }

    #[Route('/{id<\d+>}', name: 'sales_get', methods: ['GET'])]
    #[IsGranted('sales.list.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->saleService->get($id));
    }

    #[Route('', name: 'sales_create', methods: ['POST'])]
    #[IsGranted('sales.list.create')]
    public function create(#[MapRequestPayload] SalePayload $payload): JsonResponse
    {
        return new JsonResponse($this->saleService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'sales_update', methods: ['PUT'])]
    #[IsGranted('sales.list.edit')]
    public function update(int $id, #[MapRequestPayload] SalePayload $payload): JsonResponse
    {
        return new JsonResponse($this->saleService->update($id, $payload));
    }

    #[Route('/{id<\d+>}/reserve', name: 'sales_reserve', methods: ['POST'])]
    #[IsGranted('sales.list.edit')]
    public function reserve(int $id, Request $request): JsonResponse
    {
        $data = $request->toArray();

        return new JsonResponse($this->saleService->reserve($id, $data['expiresAt'] ?? null));
    }

    #[Route('/{id<\d+>}/complete', name: 'sales_complete', methods: ['POST'])]
    #[IsGranted('sales.list.edit')]
    public function complete(int $id): JsonResponse
    {
        return new JsonResponse($this->saleService->complete($id));
    }

    #[Route('/{id<\d+>}/payments', name: 'sales_add_payment', methods: ['POST'])]
    #[IsGranted('sales.payments.create')]
    public function addPayment(int $id, Request $request): JsonResponse
    {
        $data = $request->toArray();

        return new JsonResponse($this->saleService->addPayment(
            $id,
            (float) ($data['amount'] ?? 0),
            isset($data['paymentMethodId']) ? (int) $data['paymentMethodId'] : null,
            $data['reference'] ?? null,
        ), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/cancel', name: 'sales_cancel', methods: ['POST'])]
    #[IsGranted('sales.list.cancel')]
    public function cancel(int $id): JsonResponse
    {
        return new JsonResponse($this->saleService->cancel($id));
    }
}
