<?php

declare(strict_types=1);

namespace App\Module\Supplier\Controller;

use App\Module\Supplier\Dto\SupplierPayload;
use App\Module\Supplier\Service\SupplierService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/suppliers')]
#[OA\Tag(name: 'Proveedores')]
final class SupplierController
{
    public function __construct(private readonly SupplierService $supplierService)
    {
    }

    #[Route('', name: 'suppliers_list', methods: ['GET'])]
    #[IsGranted('suppliers.list.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->supplierService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'businessName'),
            direction: $request->query->getString('direction', 'asc'),
        ));
    }

    #[Route('/{id<\d+>}', name: 'suppliers_get', methods: ['GET'])]
    #[IsGranted('suppliers.list.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->supplierService->get($id));
    }

    #[Route('', name: 'suppliers_create', methods: ['POST'])]
    #[IsGranted('suppliers.list.create')]
    public function create(#[MapRequestPayload] SupplierPayload $payload): JsonResponse
    {
        return new JsonResponse($this->supplierService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'suppliers_update', methods: ['PUT'])]
    #[IsGranted('suppliers.list.edit')]
    public function update(int $id, #[MapRequestPayload] SupplierPayload $payload): JsonResponse
    {
        return new JsonResponse($this->supplierService->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'suppliers_delete', methods: ['DELETE'])]
    #[IsGranted('suppliers.list.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->supplierService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
