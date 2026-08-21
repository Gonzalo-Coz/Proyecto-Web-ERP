<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Controller;

use App\Module\Invoicing\Service\InvoiceService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/invoicing/documents')]
#[OA\Tag(name: 'Facturación Electrónica')]
final class InvoiceController
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    #[Route('', name: 'invoicing_list', methods: ['GET'])]
    #[IsGranted('invoicing.documents.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->invoiceService->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            status: $request->query->getString('status', ''),
        ));
    }

    #[Route('/{id<\d+>}', name: 'invoicing_get', methods: ['GET'])]
    #[IsGranted('invoicing.documents.view')]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->invoiceService->get($id));
    }

    #[Route('/{id<\d+>}/xml', name: 'invoicing_xml', methods: ['GET'])]
    #[IsGranted('invoicing.documents.view')]
    public function xml(int $id): Response
    {
        $data = $this->invoiceService->xmlForDownload($id);
        $response = new Response($data['xml'], Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$data['filename'].'"');

        return $response;
    }

    #[Route('', name: 'invoicing_issue', methods: ['POST'])]
    #[IsGranted('invoicing.documents.create')]
    public function issue(Request $request): JsonResponse
    {
        $data = $request->toArray();

        try {
            return new JsonResponse(
                $this->invoiceService->issueForSale((int) ($data['saleId'] ?? 0), (string) ($data['docType'] ?? '')),
                Response::HTTP_CREATED,
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            // Muestra el motivo real (ej. "La boleta a Público General solo hasta S/700")
            // en vez del texto genérico "Unprocessable Content".
            return new JsonResponse(['detail' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return new JsonResponse(['detail' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id<\d+>}/resend', name: 'invoicing_resend', methods: ['POST'])]
    #[IsGranted('invoicing.documents.create')]
    public function resend(int $id): JsonResponse
    {
        return new JsonResponse($this->invoiceService->resend($id));
    }

    #[Route('/{id<\d+>}/consult', name: 'invoicing_consult', methods: ['POST'])]
    #[IsGranted('invoicing.documents.create')]
    public function consult(int $id): JsonResponse
    {
        return new JsonResponse($this->invoiceService->consult($id));
    }

    #[Route('/{id<\d+>}/annul', name: 'invoicing_annul', methods: ['POST'])]
    #[IsGranted('invoicing.documents.create')]
    public function annul(int $id, Request $request): JsonResponse
    {
        $reason = (string) ($request->toArray()['reason'] ?? '');

        return new JsonResponse($this->invoiceService->annul($id, $reason));
    }
}
