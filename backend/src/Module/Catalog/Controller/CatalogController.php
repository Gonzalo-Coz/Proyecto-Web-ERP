<?php

declare(strict_types=1);

namespace App\Module\Catalog\Controller;

use App\Module\Catalog\Dto\CatalogItemPayload;
use App\Module\Catalog\Entity\CatalogItem;
use App\Module\Catalog\Service\CatalogService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catálogos configurables (§17). El {type} de la ruta está limitado a los
 * tipos declarados en CatalogItem::TYPES.
 */
#[Route('/api/v1/catalogs/{type}', requirements: ['type' => '[a-z_]+'])]
#[OA\Tag(name: 'Configuración — Catálogos')]
final class CatalogController
{
    public function __construct(private readonly CatalogService $catalogService)
    {
    }

    #[Route('', name: 'catalogs_list', methods: ['GET'])]
    #[IsGranted('settings.catalogs.view')]
    public function list(string $type): JsonResponse
    {
        $this->assertType($type);

        return new JsonResponse(['data' => $this->catalogService->list($type)]);
    }

    #[Route('', name: 'catalogs_create', methods: ['POST'])]
    #[IsGranted('settings.catalogs.create')]
    public function create(string $type, #[MapRequestPayload] CatalogItemPayload $payload): JsonResponse
    {
        $this->assertType($type);

        return new JsonResponse($this->catalogService->create($type, $payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'catalogs_update', methods: ['PUT'])]
    #[IsGranted('settings.catalogs.edit')]
    public function update(string $type, int $id, #[MapRequestPayload] CatalogItemPayload $payload): JsonResponse
    {
        $this->assertType($type);

        return new JsonResponse($this->catalogService->update($type, $id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'catalogs_delete', methods: ['DELETE'])]
    #[IsGranted('settings.catalogs.delete')]
    public function delete(string $type, int $id): JsonResponse
    {
        $this->assertType($type);
        $this->catalogService->delete($type, $id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function assertType(string $type): void
    {
        if (!in_array($type, CatalogItem::TYPES, true)) {
            throw new NotFoundHttpException(sprintf('Catálogo "%s" no existe.', $type));
        }
    }
}
