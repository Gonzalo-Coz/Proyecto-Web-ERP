<?php

declare(strict_types=1);

namespace App\Module\CashRegister\Controller;

use App\Module\CashRegister\Service\CashService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/cash')]
#[OA\Tag(name: 'Caja')]
final class CashController
{
    public function __construct(private readonly CashService $cashService)
    {
    }

    #[Route('/current', name: 'cash_current', methods: ['GET'])]
    #[IsGranted('cash.sessions.view')]
    public function current(): JsonResponse
    {
        return new JsonResponse($this->cashService->current());
    }

    #[Route('/sessions', name: 'cash_sessions', methods: ['GET'])]
    #[IsGranted('cash.sessions.view')]
    public function sessions(Request $request): JsonResponse
    {
        return new JsonResponse($this->cashService->listSessions(
            $request->query->getInt('page', 1),
            $request->query->getInt('perPage', 10),
        ));
    }

    #[Route('/sessions/open', name: 'cash_open', methods: ['POST'])]
    #[IsGranted('cash.sessions.create')]
    public function open(Request $request): JsonResponse
    {
        $data = $request->toArray();

        return new JsonResponse(
            $this->cashService->open((float) ($data['openingAmount'] ?? -1), $data['notes'] ?? null),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/sessions/{id<\d+>}/close', name: 'cash_close', methods: ['POST'])]
    #[IsGranted('cash.sessions.edit')]
    public function close(int $id, Request $request): JsonResponse
    {
        $data = $request->toArray();

        return new JsonResponse(
            $this->cashService->close($id, (float) ($data['countedAmount'] ?? -1), $data['notes'] ?? null),
        );
    }

    #[Route('/sessions/{id<\d+>}/movements', name: 'cash_session_movements', methods: ['GET'])]
    #[IsGranted('cash.movements.view')]
    public function movements(int $id): JsonResponse
    {
        return new JsonResponse(['data' => $this->cashService->movements($id)]);
    }

    #[Route('/movements', name: 'cash_movement_create', methods: ['POST'])]
    #[IsGranted('cash.movements.create')]
    public function createMovement(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $movement = $this->cashService->registerMovement(
            (string) ($data['movementType'] ?? ''),
            (float) ($data['amount'] ?? 0),
            isset($data['paymentMethodId']) ? (int) $data['paymentMethodId'] : null,
            (string) ($data['concept'] ?? ''),
        );

        return new JsonResponse(['id' => $movement->getId()], Response::HTTP_CREATED);
    }
}
