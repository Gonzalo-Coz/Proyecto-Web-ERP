<?php

declare(strict_types=1);

namespace App\Module\Customer\Controller;

use App\Module\Customer\Dto\CustomerTypePayload;
use App\Module\Customer\Service\CustomerTypeService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/customer-types')]
#[OA\Tag(name: 'Clientes — Tipos')]
final class CustomerTypeController
{
    public function __construct(private readonly CustomerTypeService $service)
    {
    }

    #[Route('', name: 'customer_types_list', methods: ['GET'])]
    #[IsGranted('customers.list.view')]
    public function list(): JsonResponse
    {
        return new JsonResponse(['data' => $this->service->list()]);
    }

    #[Route('', name: 'customer_types_create', methods: ['POST'])]
    #[IsGranted('customers.list.create')]
    public function create(#[MapRequestPayload] CustomerTypePayload $payload): JsonResponse
    {
        return new JsonResponse($this->service->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'customer_types_update', methods: ['PUT'])]
    #[IsGranted('customers.list.edit')]
    public function update(int $id, #[MapRequestPayload] CustomerTypePayload $payload): JsonResponse
    {
        return new JsonResponse($this->service->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'customer_types_delete', methods: ['DELETE'])]
    #[IsGranted('customers.list.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
