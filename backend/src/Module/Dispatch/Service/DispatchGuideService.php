<?php

declare(strict_types=1);

namespace App\Module\Dispatch\Service;

use App\Module\Dispatch\Dto\DispatchGuidePayload;
use App\Module\Dispatch\Entity\DispatchGuide;
use App\Module\Dispatch\Repository\DispatchGuideRepository;
use App\Module\Sales\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Gestión de Guías de Remisión (Fase 1: alta y consulta internas).
 * La emisión a SUNAT (NubeFact generar_guia) se conecta en la Fase 2.
 */
final class DispatchGuideService
{
    private const SERIES = 'TTT1';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DispatchGuideRepository $repository,
        private readonly SaleRepository $saleRepository,
        private readonly NubefactGuideClient $guideClient,
    ) {
    }

    /** Emite la guía a SUNAT vía NubeFact (generar_guia). */
    public function emit(int $id): array
    {
        $guide = $this->repository->find($id) ?? throw new NotFoundHttpException('Guía no encontrada.');
        if ($guide->getStatus() === 'ACEPTADO') {
            throw new ConflictHttpException('La guía ya fue aceptada por SUNAT.');
        }
        try {
            $result = $this->guideClient->emit($guide);
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('No se pudo emitir la guía: '.$e->getMessage());
        }
        $guide->applyProviderResult(
            $result['status'], $result['hash'], $result['qrData'],
            $result['pdfUrl'], $result['xmlUrl'], $result['errorMessage'], $result['raw'],
        );
        $this->entityManager->flush();

        return $this->toArray($guide);
    }

    /** Consulta el estado real de la guía en NubeFact y lo sincroniza. */
    public function consult(int $id): array
    {
        $guide = $this->repository->find($id) ?? throw new NotFoundHttpException('Guía no encontrada.');
        try {
            $result = $this->guideClient->consult($guide);
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('No se pudo consultar la guía: '.$e->getMessage());
        }
        $guide->applyProviderResult(
            $result['status'], $result['hash'], $result['qrData'],
            $result['pdfUrl'], $result['xmlUrl'], $result['errorMessage'], $result['raw'],
        );
        $this->entityManager->flush();

        return $this->toArray($guide);
    }

    public function create(DispatchGuidePayload $payload): array
    {
        $items = $this->normalizeItems($payload->items);
        if ($items === []) {
            throw new UnprocessableEntityHttpException('La guía debe tener al menos un ítem con descripción y cantidad.');
        }
        if ($payload->transportMode === '01' && ($payload->carrierRuc === null || trim((string) $payload->carrierRuc) === '')) {
            throw new UnprocessableEntityHttpException('Transporte público: el RUC del transportista es obligatorio.');
        }
        if ($payload->transportMode === '02' && ($payload->vehiclePlate === null || trim((string) $payload->vehiclePlate) === '')) {
            throw new UnprocessableEntityHttpException('Transporte privado: la placa del vehículo es obligatoria.');
        }

        $guide = new DispatchGuide(
            correlative: $this->repository->nextCorrelative(self::SERIES),
            issueDate: new \DateTimeImmutable('today', new \DateTimeZone('America/Lima')),
            transferDate: new \DateTimeImmutable($payload->transferDate),
            motive: $payload->motive,
            recipientDocType: $payload->recipientDocType,
            recipientDocNumber: $payload->recipientDocNumber,
            recipientName: $payload->recipientName,
            originAddress: $payload->originAddress,
            destinationAddress: $payload->destinationAddress,
            items: $items,
        );
        $guide->setOriginUbigeo($this->nullify($payload->originUbigeo));
        $guide->setDestinationUbigeo($this->nullify($payload->destinationUbigeo));
        $guide->setTransportMode($payload->transportMode);
        $guide->setCarrierRuc($this->nullify($payload->carrierRuc));
        $guide->setCarrierName($this->nullify($payload->carrierName));
        $guide->setVehiclePlate($this->nullify($payload->vehiclePlate));
        $guide->setDriverLicense($this->nullify($payload->driverLicense));
        $guide->setDriverName($this->nullify($payload->driverName));
        $guide->setTotalWeight($payload->totalWeight);
        $guide->setPackages($payload->packages);
        $guide->setObservations($this->nullify($payload->observations));

        if ($payload->saleId !== null) {
            $sale = $this->saleRepository->find($payload->saleId);
            if ($sale !== null) {
                $guide->setSale($sale);
            }
        }

        $this->entityManager->persist($guide);
        $this->entityManager->flush();

        return $this->toArray($guide);
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $status): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $qb = $this->repository->createQueryBuilder('g')
            ->orderBy('g.correlative', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(g.recipientName) LIKE :s OR g.recipientDocNumber LIKE :s OR CAST(g.correlative AS text) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if ($status !== '' && in_array($status, DispatchGuide::STATUSES, true)) {
            $qb->andWhere('g.status = :st')->setParameter('st', $status);
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map(fn (DispatchGuide $g) => $this->toArray($g), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    public function get(int $id): array
    {
        $guide = $this->repository->find($id) ?? throw new NotFoundHttpException('Guía no encontrada.');

        return $this->toArray($guide);
    }

    /**
     * @param array<int, array<string, mixed>> $raw
     *
     * @return list<array{codigo: string, descripcion: string, cantidad: float, unidad: string}>
     */
    private function normalizeItems(array $raw): array
    {
        $items = [];
        foreach ($raw as $it) {
            $desc = trim((string) ($it['descripcion'] ?? ''));
            $qty = (float) ($it['cantidad'] ?? 0);
            if ($desc === '' || $qty <= 0) {
                continue;
            }
            $items[] = [
                'codigo' => trim((string) ($it['codigo'] ?? '')),
                'descripcion' => $desc,
                'cantidad' => $qty,
                'unidad' => trim((string) ($it['unidad'] ?? 'NIU')) ?: 'NIU',
            ];
        }

        return $items;
    }

    private function nullify(?string $s): ?string
    {
        $s = trim((string) $s);

        return $s === '' ? null : $s;
    }

    public function toArray(DispatchGuide $g): array
    {
        return [
            'id' => $g->getId(),
            'fullNumber' => $g->getFullNumber(),
            'series' => $g->getSeries(),
            'correlative' => $g->getCorrelative(),
            'issueDate' => $g->getIssueDate()->format('Y-m-d'),
            'transferDate' => $g->getTransferDate()->format('Y-m-d'),
            'motive' => $g->getMotive(),
            'motiveName' => DispatchGuide::MOTIVOS[$g->getMotive()] ?? $g->getMotive(),
            'recipientDocType' => $g->getRecipientDocType(),
            'recipientDocNumber' => $g->getRecipientDocNumber(),
            'recipientName' => $g->getRecipientName(),
            'originAddress' => $g->getOriginAddress(),
            'originUbigeo' => $g->getOriginUbigeo(),
            'destinationAddress' => $g->getDestinationAddress(),
            'destinationUbigeo' => $g->getDestinationUbigeo(),
            'transportMode' => $g->getTransportMode(),
            'transportModeName' => DispatchGuide::MODALIDADES[$g->getTransportMode()] ?? $g->getTransportMode(),
            'carrierRuc' => $g->getCarrierRuc(),
            'carrierName' => $g->getCarrierName(),
            'vehiclePlate' => $g->getVehiclePlate(),
            'driverLicense' => $g->getDriverLicense(),
            'driverName' => $g->getDriverName(),
            'totalWeight' => $g->getTotalWeight(),
            'weightUnit' => $g->getWeightUnit(),
            'packages' => $g->getPackages(),
            'items' => $g->getItems(),
            'saleId' => $g->getSale()?->getId(),
            'saleNumber' => $g->getSale()?->getSaleNumber(),
            'observations' => $g->getObservations(),
            'status' => $g->getStatus(),
            'hash' => $g->getHash(),
            'qrData' => $g->getQrData(),
            'pdfUrl' => $g->getPdfUrl(),
            'xmlUrl' => $g->getXmlUrl(),
            'errorMessage' => $g->getErrorMessage(),
        ];
    }
}
