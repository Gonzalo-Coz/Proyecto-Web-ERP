<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Controller;

use App\Module\Purchasing\Service\InvoiceImportService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/purchases/import')]
#[OA\Tag(name: 'Compras — Importar XML')]
final class InvoiceImportController
{
    public function __construct(private readonly InvoiceImportService $importService)
    {
    }

    /** Vista previa: lee el XML y devuelve los datos extraídos (no guarda nada). */
    #[Route('/preview', name: 'purchases_import_preview', methods: ['POST'])]
    #[IsGranted('purchases.list.create')]
    public function preview(Request $request): JsonResponse
    {
        return new JsonResponse($this->importService->preview($this->readXml($request)));
    }

    /** Confirma: crea repuestos/unidades faltantes y registra la compra. */
    #[Route('/confirm', name: 'purchases_import_confirm', methods: ['POST'])]
    #[IsGranted('purchases.list.create')]
    public function confirm(Request $request): JsonResponse
    {
        return new JsonResponse($this->importService->confirm($request->toArray()), Response::HTTP_CREATED);
    }

    private function readXml(Request $request): string
    {
        $file = $request->files->get('file');
        if ($file instanceof UploadedFile) {
            $content = (string) file_get_contents($file->getPathname());
        } else {
            $content = $request->getContent();
        }
        if (trim($content) === '') {
            throw new UnprocessableEntityHttpException('Adjunte el archivo XML en el campo "file".');
        }

        return $content;
    }
}
