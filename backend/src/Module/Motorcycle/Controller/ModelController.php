<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Controller;

use App\Module\Motorcycle\Dto\ModelPayload;
use App\Module\Motorcycle\Service\ModelService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/motorcycles/models')]
#[OA\Tag(name: 'Motocicletas — Modelos')]
final class ModelController
{
    public function __construct(private readonly ModelService $modelService)
    {
    }

    #[Route('', name: 'moto_models_list', methods: ['GET'])]
    #[IsGranted('motorcycles.models.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->modelService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'model'),
            direction: $request->query->getString('direction', 'asc'),
        ));
    }

    #[Route('/{id<\d+>}', name: 'moto_models_get', methods: ['GET'])]
    #[IsGranted('motorcycles.models.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->modelService->get($id));
    }

    #[Route('', name: 'moto_models_create', methods: ['POST'])]
    #[IsGranted('motorcycles.models.create')]
    public function create(#[MapRequestPayload] ModelPayload $payload): JsonResponse
    {
        return new JsonResponse($this->modelService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'moto_models_update', methods: ['PUT'])]
    #[IsGranted('motorcycles.models.edit')]
    public function update(int $id, #[MapRequestPayload] ModelPayload $payload): JsonResponse
    {
        return new JsonResponse($this->modelService->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'moto_models_delete', methods: ['DELETE'])]
    #[IsGranted('motorcycles.models.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->modelService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
