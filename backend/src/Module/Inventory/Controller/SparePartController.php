<?php

declare(strict_types=1);

namespace App\Module\Inventory\Controller;

use App\Module\Inventory\Dto\AdjustmentPayload;
use App\Module\Inventory\Dto\SparePartPayload;
use App\Module\Inventory\Service\SparePartImportService;
use App\Module\Inventory\Service\SparePartService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/inventory/spare-parts')]
#[OA\Tag(name: 'Inventario — Repuestos')]
final class SparePartController
{
    public function __construct(
        private readonly SparePartService $sparePartService,
        private readonly SparePartImportService $importService,
    ) {
    }

    #[Route('/import/template', name: 'parts_import_template', methods: ['GET'])]
    #[IsGranted('inventory.spare_parts.create')]
    public function importTemplate(): Response
    {
        $response = new Response($this->importService->template());
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="plantilla_productos_YIGM.csv"');

        return $response;
    }

    /** Sube la plantilla. Con ?dryRun=1 devuelve la vista previa sin guardar. */
    #[Route('/import', name: 'parts_import', methods: ['POST'])]
    #[IsGranted('inventory.spare_parts.create')]
    public function import(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('Adjunte el archivo CSV en el campo "file".');
        }

        $dryRun = $request->query->getBoolean('dryRun', true);

        return new JsonResponse($this->importService->process($file, $dryRun));
    }

    #[Route('', name: 'parts_list', methods: ['GET'])]
    #[IsGranted('inventory.spare_parts.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->sparePartService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'description'),
            direction: $request->query->getString('direction', 'asc'),
            compatibleModelId: $request->query->getInt('compatibleModelId', 0),
            stockFilter: $request->query->getString('stockFilter', ''),
        ));
    }

    #[Route('/{id<\d+>}', name: 'parts_get', methods: ['GET'])]
    #[IsGranted('inventory.spare_parts.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->sparePartService->get($id));
    }

    #[Route('', name: 'parts_create', methods: ['POST'])]
    #[IsGranted('inventory.spare_parts.create')]
    public function create(#[MapRequestPayload] SparePartPayload $payload): JsonResponse
    {
        return new JsonResponse($this->sparePartService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'parts_update', methods: ['PUT'])]
    #[IsGranted('inventory.spare_parts.edit')]
    public function update(int $id, #[MapRequestPayload] SparePartPayload $payload): JsonResponse
    {
        return new JsonResponse($this->sparePartService->update($id, $payload));
    }

    #[Route('/{id<\d+>}', name: 'parts_delete', methods: ['DELETE'])]
    #[IsGranted('inventory.spare_parts.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->sparePartService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id<\d+>}/adjust', name: 'parts_adjust', methods: ['POST'])]
    #[IsGranted('inventory.adjustments.create')]
    public function adjust(int $id, #[MapRequestPayload] AdjustmentPayload $payload): JsonResponse
    {
        return new JsonResponse($this->sparePartService->adjust($id, $payload));
    }

    #[Route('/{id<\d+>}/kardex', name: 'parts_kardex', methods: ['GET'])]
    #[IsGranted('inventory.kardex.view')]
    public function kardex(int $id, Request $request): JsonResponse
    {
        return new JsonResponse($this->sparePartService->kardex(
            $id,
            $request->query->getInt('page', 1),
            $request->query->getInt('perPage', 10),
        ));
    }
}
