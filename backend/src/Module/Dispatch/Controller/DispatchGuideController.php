<?php

declare(strict_types=1);

namespace App\Module\Dispatch\Controller;

use App\Module\Dispatch\Dto\DispatchGuidePayload;
use App\Module\Dispatch\Service\DispatchGuideService;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/dispatch-guides')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DispatchGuideController
{
    public function __construct(private readonly DispatchGuideService $service)
    {
    }

    #[Route('', name: 'dispatch_guides_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->service->list(
            (int) $request->query->get('page', 1),
            (int) $request->query->get('perPage', 15),
            (string) $request->query->get('search', ''),
            (string) $request->query->get('status', ''),
        ));
    }

    #[Route('/{id<\d+>}', name: 'dispatch_guides_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->service->get($id));
    }

    #[Route('', name: 'dispatch_guides_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] DispatchGuidePayload $payload): JsonResponse
    {
        return new JsonResponse($this->service->create($payload), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/emit', name: 'dispatch_guides_emit', methods: ['POST'])]
    public function emit(int $id): JsonResponse
    {
        return new JsonResponse($this->service->emit($id));
    }

    #[Route('/{id<\d+>}/consult', name: 'dispatch_guides_consult', methods: ['POST'])]
    public function consult(int $id): JsonResponse
    {
        return new JsonResponse($this->service->consult($id));
    }
}
