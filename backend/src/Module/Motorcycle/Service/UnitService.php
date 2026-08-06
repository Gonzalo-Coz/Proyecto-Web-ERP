<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Service;

use App\Module\Motorcycle\Dto\UnitPayload;
use App\Module\Motorcycle\Entity\MotorcycleModel;
use App\Module\Motorcycle\Entity\MotorcycleUnit;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Module\Motorcycle\Repository\MotorcycleUnitRepository;
use App\Module\Supplier\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UnitService
{
    private const SORTABLE = ['internalCode', 'vin', 'status', 'entryDate', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MotorcycleUnitRepository $unitRepository,
        private readonly MotorcycleModelRepository $modelRepository,
        private readonly SupplierRepository $supplierRepository,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction, string $status): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'internalCode';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->unitRepository->createQueryBuilder('u')
            ->join('u.model', 'm')->addSelect('m')
            ->join('m.brand', 'b')->addSelect('b')
            ->orderBy('u.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            // Búsqueda "por cualquier información" de la moto.
            $qb->andWhere(
                'LOWER(u.vin) LIKE :s OR LOWER(u.internalCode) LIKE :s OR LOWER(u.engineNumber) LIKE :s '
                .'OR LOWER(u.chassisNumber) LIKE :s OR LOWER(u.series) LIKE :s OR LOWER(u.color) LIKE :s '
                .'OR LOWER(m.model) LIKE :s OR LOWER(m.version) LIKE :s OR LOWER(b.name) LIKE :s',
            )->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        if ($status !== '' && in_array($status, MotorcycleUnit::STATUSES, true)) {
            $qb->andWhere('u.status = :status')->setParameter('status', $status);
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->toArray(...), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id));
    }

    public function create(UnitPayload $payload): array
    {
        $this->assertVinUnique($payload->vin, null);
        $this->assertEngineNumberUnique($payload->engineNumber, null);

        $unit = new MotorcycleUnit($payload->internalCode, $payload->vin, $this->findModel($payload->modelId), $payload->color);
        $this->apply($unit, $payload);

        $this->entityManager->persist($unit);
        $this->entityManager->flush();

        return $this->toArray($unit);
    }

    public function update(int $id, UnitPayload $payload): array
    {
        $unit = $this->find($id);

        if ($unit->isSold()) {
            throw new ConflictHttpException('Una motocicleta vendida no puede modificarse; su expediente es histórico.');
        }
        if (strtoupper($payload->vin) !== $unit->getVin()) {
            throw new ConflictHttpException('El VIN no puede modificarse una vez registrado.');
        }
        $this->assertEngineNumberUnique($payload->engineNumber, $id);

        $unit->setInternalCode($payload->internalCode);
        $unit->setModel($this->findModel($payload->modelId));
        $unit->setColor($payload->color);
        $this->apply($unit, $payload);

        $this->entityManager->flush();

        return $this->toArray($unit);
    }

    /** Cambio manual de estado, restringido a estados no gestionados por el sistema. */
    public function changeStatus(int $id, string $status): array
    {
        $unit = $this->find($id);

        if (!in_array($status, MotorcycleUnit::STATUSES, true)) {
            throw new UnprocessableEntityHttpException('Estado inválido.');
        }
        if (in_array($status, MotorcycleUnit::SYSTEM_STATUSES, true)) {
            throw new ConflictHttpException(sprintf('El estado %s lo asigna el sistema (ventas/taller), no puede fijarse manualmente.', $status));
        }
        if ($unit->isSold()) {
            throw new ConflictHttpException('Una motocicleta vendida no cambia de estado manualmente.');
        }

        $unit->setStatus($status);
        $this->entityManager->flush();

        return $this->toArray($unit);
    }

    public function delete(int $id): void
    {
        $unit = $this->find($id);
        if ($unit->isSold()) {
            throw new ConflictHttpException('Una motocicleta vendida no puede eliminarse.');
        }

        $unit->markDeleted();
        $unit->setStatus('BAJA');
        $this->entityManager->flush();
    }

    private function find(int $id): MotorcycleUnit
    {
        return $this->unitRepository->find($id)
            ?? throw new NotFoundHttpException('Unidad no encontrada.');
    }

    private function findModel(int $modelId): MotorcycleModel
    {
        $model = $this->modelRepository->find($modelId);
        if ($model === null || !$model->isActive()) {
            throw new UnprocessableEntityHttpException('Modelo inválido o inactivo.');
        }

        return $model;
    }

    private function assertVinUnique(string $vin, ?int $exceptId): void
    {
        $existing = $this->unitRepository->findOneByVin($vin);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El VIN %s ya está registrado (unidad %s).', strtoupper($vin), $existing->getInternalCode()));
        }
    }

    private function assertEngineNumberUnique(?string $engineNumber, ?int $exceptId): void
    {
        if ($engineNumber === null || $engineNumber === '') {
            return;
        }
        $existing = $this->unitRepository->findOneByEngineNumber($engineNumber);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El número de motor %s ya está registrado.', strtoupper($engineNumber)));
        }
    }

    private function apply(MotorcycleUnit $unit, UnitPayload $payload): void
    {
        $unit->setEngineNumber($payload->engineNumber ?: null);
        $unit->setChassisNumber($payload->chassisNumber);
        $unit->setSeries($payload->series);
        $unit->setManufactureYear($payload->manufactureYear);
        if ($payload->entryDate !== null) {
            $unit->setEntryDate(new \DateTimeImmutable($payload->entryDate));
        }
        $unit->setPurchaseDate($payload->purchaseDate !== null ? new \DateTimeImmutable($payload->purchaseDate) : null);
        $unit->setSupplier($payload->supplierId !== null ? $this->supplierRepository->find($payload->supplierId) : null);
        $unit->setPurchasePrice($payload->purchasePrice !== null ? number_format($payload->purchasePrice, 2, '.', '') : null);
        $unit->setSalePrice($payload->salePrice !== null ? number_format($payload->salePrice, 2, '.', '') : null);
        $unit->setLocation($payload->location);
        $unit->setNotes($payload->notes);
        $unit->setDuaNumber($payload->duaNumber);
        $unit->setDuaItem($payload->duaItem);
    }

    public function toArray(MotorcycleUnit $unit): array
    {
        return [
            'id' => $unit->getId(),
            'internalCode' => $unit->getInternalCode(),
            'vin' => $unit->getVin(),
            'engineNumber' => $unit->getEngineNumber(),
            'chassisNumber' => $unit->getChassisNumber(),
            'series' => $unit->getSeries(),
            'modelId' => $unit->getModel()->getId(),
            'modelName' => $unit->getModel()->getFullName(),
            'modelYear' => $unit->getModel()->getModelYear(),
            'manufactureYear' => $unit->getManufactureYear(),
            'color' => $unit->getColor(),
            'entryDate' => $unit->getEntryDate()->format('Y-m-d'),
            'purchaseDate' => $unit->getPurchaseDate()?->format('Y-m-d'),
            'supplierId' => $unit->getSupplier()?->getId(),
            'supplierName' => $unit->getSupplier()?->getBusinessName(),
            'purchasePrice' => $unit->getPurchasePrice(),
            'salePrice' => $unit->getSalePrice(),
            'status' => $unit->getStatus(),
            'location' => $unit->getLocation(),
            'notes' => $unit->getNotes(),
            'duaNumber' => $unit->getDuaNumber(),
            'duaItem' => $unit->getDuaItem(),
        ];
    }
}
