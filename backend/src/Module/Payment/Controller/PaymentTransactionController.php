<?php

declare(strict_types=1);

namespace App\Module\Payment\Controller;

use App\Module\Payment\Dto\PaymentTransactionPayload;
use App\Module\Payment\Service\PaymentGatewayService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Transacciones de pasarela de pago (Adición A6). Controller fino.
 */
#[Route('/api/v1/payments/transactions')]
#[OA\Tag(name: 'Pagos — Pasarela')]
final class PaymentTransactionController
{
    public function __construct(private readonly PaymentGatewayService $service)
    {
    }

    #[Route('', name: 'payment_tx_list', methods: ['GET'])]
    #[IsGranted('payments.gateway.view')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->service->list(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 10),
            search: trim($request->query->getString('search', '')),
            status: trim($request->query->getString('status', '')),
            sort: $request->query->getString('sort', 'createdAt'),
            direction: $request->query->getString('direction', 'desc'),
        ));
    }

    #[Route('', name: 'payment_tx_register', methods: ['POST'])]
    #[IsGranted('payments.gateway.create')]
    public function register(#[MapRequestPayload] PaymentTransactionPayload $payload): JsonResponse
    {
        return new JsonResponse($this->service->register($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/approve', name: 'payment_tx_approve', methods: ['POST'])]
    #[IsGranted('payments.gateway.validate')]
    public function approve(int $id): JsonResponse
    {
        return new JsonResponse($this->service->validate($id, true));
    }

    #[Route('/{id<\d+>}/reject', name: 'payment_tx_reject', methods: ['POST'])]
    #[IsGranted('payments.gateway.validate')]
    public function reject(int $id): JsonResponse
    {
        return new JsonResponse($this->service->validate($id, false));
    }
}
