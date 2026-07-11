<?php

declare(strict_types=1);

namespace App\Module\Customer\Controller;

use App\Module\Customer\Dto\CustomerPayload;
use App\Module\Customer\Service\CustomerService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/customers')]
#[OA\Tag(name: 'Clientes')]
final class CustomerController
{
    public function __construct(private readonly CustomerService $customerService)
    {
    }

    #[Route('', name: 'customers_list', methods: ['GET'])]
    #[IsGranted('customers.list.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->customerService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'name'),
            direction: $request->query->getString('direction', 'asc'),
        ));
    }

    #[Route('/{id<\d+>}', name: 'customers_get', methods: ['GET'])]
    #[IsGranted('customers.list.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->customerService->get($id));
    }

    #[Route('', name: 'customers_create', methods: ['POST'])]
    #[IsGranted('customers.list.create')]
    public function create(#[MapRequestPayload] CustomerPayload $payload): JsonResponse
    {
        return new JsonResponse($this->customerService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'customers_update', methods: ['PUT'])]
    #[IsGranted('customers.list.edit')]
    public function update(int $id, #[MapRequestPayload] CustomerPayload $payload): JsonResponse
    {
        return new JsonResponse($this->customerService->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'customers_delete', methods: ['DELETE'])]
    #[IsGranted('customers.list.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->customerService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
