<?php

declare(strict_types=1);

namespace App\Module\Reporting\Controller;

use App\Module\Reporting\Service\ReportService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/reports')]
#[OA\Tag(name: 'Reportes')]
final class ReportController
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    #[Route('/{type}', name: 'reports_generate', methods: ['GET'], requirements: ['type' => '[a-z]+'])]
    #[IsGranted('reports.main.view')]
    public function generate(string $type, Request $request): JsonResponse
    {
        $from = $request->query->getString('from', date('Y-m-01'));
        $to = $request->query->getString('to', date('Y-m-d'));

        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                throw new UnprocessableEntityHttpException(sprintf('Parámetro "%s" inválido (YYYY-MM-DD).', $name));
            }
        }

        return new JsonResponse($this->reportService->generate($type, $from, $to));
    }
}
