<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Controller;

use App\Module\Motorcycle\Dto\UnitPayload;
use App\Module\Motorcycle\Service\UnitImportService;
use App\Module\Motorcycle\Service\UnitService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/motorcycles/units')]
#[OA\Tag(name: 'Motocicletas — Unidades')]
final class UnitController
{
    public function __construct(
        private readonly UnitService $unitService,
        private readonly UnitImportService $importService,
    ) {
    }

    #[Route('/import/template', name: 'moto_units_import_template', methods: ['GET'])]
    #[IsGranted('motorcycles.units.create')]
    public function importTemplate(): Response
    {
        $response = new Response($this->importService->template());
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="plantilla_motos_YIGM.csv"');

        return $response;
    }

    #[Route('/import', name: 'moto_units_import', methods: ['POST'])]
    #[IsGranted('motorcycles.units.create')]
    public function import(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('Adjunte el archivo CSV en el campo "file".');
        }

        return new JsonResponse($this->importService->process($file, $request->query->getBoolean('dryRun', true)));
    }

    #[Route('', name: 'moto_units_list', methods: ['GET'])]
    #[IsGranted('motorcycles.units.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->unitService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            sort: $request->query->getString('sort', 'internalCode'),
            direction: $request->query->getString('direction', 'asc'),
            status: $request->query->getString('status', ''),
        ));
    }

    #[Route('/{id<\d+>}', name: 'moto_units_get', methods: ['GET'])]
    #[IsGranted('motorcycles.units.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->unitService->get($id));
    }

    #[Route('', name: 'moto_units_create', methods: ['POST'])]
    #[IsGranted('motorcycles.units.create')]
    public function create(#[MapRequestPayload] UnitPayload $payload): JsonResponse
    {
        return new JsonResponse($this->unitService->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'moto_units_update', methods: ['PUT'])]
    #[IsGranted('motorcycles.units.edit')]
    public function update(int $id, #[MapRequestPayload] UnitPayload $payload): JsonResponse
    {
        return new JsonResponse($this->unitService->update($id, $payload));
    }

    #[Route('/{id<\d+>}/status', name: 'moto_units_status', methods: ['PATCH'])]
    #[IsGranted('motorcycles.units.edit')]
    public function changeStatus(int $id, Request $request): JsonResponse
    {
        $status = (string) ($request->toArray()['status'] ?? '');

        return new JsonResponse($this->unitService->changeStatus($id, $status));
    }

    #[Route('/{id<\d+>}', name: 'moto_units_delete', methods: ['DELETE'])]
    #[IsGranted('motorcycles.units.delete')]
    public function delete(int $id): JsonResponse
    {
        $this->unitService->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
