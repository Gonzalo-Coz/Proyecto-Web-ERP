<?php

declare(strict_types=1);

namespace App\Module\Workshop\Controller;

use App\Module\Workshop\Service\WorkshopService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/workshop/orders')]
#[OA\Tag(name: 'Taller')]
final class WorkshopController
{
    public function __construct(private readonly WorkshopService $workshopService)
    {
    }

    #[Route('', name: 'workshop_list', methods: ['GET'])]
    #[IsGranted('workshop.orders.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->workshopService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            status: $request->query->getString('status', ''),
        ));
    }

    #[Route('/{id<\d+>}', name: 'workshop_get', methods: ['GET'])]
    #[IsGranted('workshop.orders.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->workshopService->get($id));
    }

    #[Route('', name: 'workshop_create', methods: ['POST'])]
    #[IsGranted('workshop.orders.create')]
    public function create(Request $request): JsonResponse
    {
        return new JsonResponse($this->workshopService->create($request->toArray()), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/items', name: 'workshop_add_item', methods: ['POST'])]
    #[IsGranted('workshop.orders.edit')]
    public function addItem(int $id, Request $request): JsonResponse
    {
        return new JsonResponse($this->workshopService->addItem($id, $request->toArray()), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/maintenance-plan', name: 'workshop_apply_plan', methods: ['POST'])]
    #[IsGranted('workshop.orders.edit')]
    public function applyMaintenancePlan(int $id, Request $request): JsonResponse
    {
        return new JsonResponse($this->workshopService->applyMaintenancePlan($id, $request->toArray()), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/items/{itemId<\d+>}', name: 'workshop_remove_item', methods: ['DELETE'])]
    #[IsGranted('workshop.orders.edit')]
    public function removeItem(int $id, int $itemId): JsonResponse
    {
        return new JsonResponse($this->workshopService->removeItem($id, $itemId));
    }

    #[Route('/{id<\d+>}/status', name: 'workshop_status', methods: ['PATCH'])]
    #[IsGranted('workshop.orders.edit')]
    public function changeStatus(int $id, Request $request): JsonResponse
    {
        return new JsonResponse($this->workshopService->changeStatus($id, (string) ($request->toArray()['status'] ?? '')));
    }

    #[Route('/{id<\d+>}/invoice', name: 'workshop_invoice', methods: ['POST'])]
    #[IsGranted('workshop.orders.approve')]
    public function invoice(int $id): JsonResponse
    {
        return new JsonResponse($this->workshopService->invoice($id));
    }

    #[Route('/{id<\d+>}/cancel', name: 'workshop_cancel', methods: ['POST'])]
    #[IsGranted('workshop.orders.edit')]
    public function cancel(int $id, Request $request): JsonResponse
    {
        return new JsonResponse($this->workshopService->cancel($id, (string) ($request->toArray()['reason'] ?? '')));
    }
}
