<?php

declare(strict_types=1);

namespace App\Module\Reporting\Controller;

use App\Module\Reporting\Service\ReportService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/stock-ventas-motos.xlsx', name: 'reports_stock_ventas_xlsx', methods: ['GET'])]
    #[IsGranted('reports.main.view')]
    public function stockVentasXlsx(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

        return $this->xlsx(
            $this->reportService->stockVentasXlsx($from, $to),
            'stock_y_ventas_motos.xlsx',
        );
    }

    #[Route('/repuestos-yamaha.xlsx', name: 'reports_repuestos_xlsx', methods: ['GET'])]
    #[IsGranted('reports.main.view')]
    public function repuestosYamahaXlsx(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

        return $this->xlsx(
            $this->reportService->repuestosYamahaXlsx($from, $to),
            'venta_repuestos_yamaha.xlsx',
        );
    }

    #[Route('/stock-repuestos.xlsx', name: 'reports_stock_repuestos_xlsx', methods: ['GET'])]
    #[IsGranted('reports.main.view')]
    public function stockRepuestosXlsx(): Response
    {
        return $this->xlsx(
            $this->reportService->stockRepuestosXlsx(),
            'stock_repuestos_yamaha.xlsx',
        );
    }

    /** @return array{0: string, 1: string} */
    private function dateRange(Request $request): array
    {
        $from = $request->query->getString('from', date('Y-m-01'));
        $to = $request->query->getString('to', date('Y-m-d'));
        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                throw new UnprocessableEntityHttpException(sprintf('Parámetro "%s" inválido (YYYY-MM-DD).', $name));
            }
        }

        return [$from, $to];
    }

    private function xlsx(string $content, string $filename): Response
    {
        $response = new Response(
            $content,
            Response::HTTP_OK,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
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
