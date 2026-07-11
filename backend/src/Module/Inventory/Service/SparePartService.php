<?php

declare(strict_types=1);

namespace App\Module\Inventory\Service;

use App\Module\Catalog\Repository\CatalogItemRepository;
use App\Module\Inventory\Dto\AdjustmentPayload;
use App\Module\Inventory\Dto\SparePartPayload;
use App\Module\Inventory\Entity\SparePart;
use App\Module\Inventory\Repository\KardexMovementRepository;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SparePartService
{
    private const SORTABLE = ['internalCode', 'partCode', 'description', 'stock', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SparePartRepository $partRepository,
        private readonly KardexMovementRepository $kardexRepository,
        private readonly CatalogItemRepository $catalogRepository,
        private readonly MotorcycleModelRepository $modelRepository,
        private readonly StockService $stockService,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction, int $compatibleModelId, string $stockFilter): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'description';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->partRepository->createQueryBuilder('p')
            ->leftJoin('p.brand', 'b')->addSelect('b')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->orderBy('p.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(p.description) LIKE :s OR LOWER(p.internalCode) LIKE :s OR LOWER(p.partCode) LIKE :s OR p.barcode LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if ($compatibleModelId > 0) {
            $qb->join('p.compatibleModels', 'cm')->andWhere('cm.id = :mid')->setParameter('mid', $compatibleModelId);
        }
        if ($stockFilter === 'low') {
            $qb->andWhere('p.stock > 0 AND p.stock <= p.minStock');
        } elseif ($stockFilter === 'out') {
            $qb->andWhere('p.stock <= 0');
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

    public function create(SparePartPayload $payload): array
    {
        $this->assertUnique($payload->internalCode, $payload->partCode, null);

        $part = new SparePart($payload->internalCode, $payload->partCode, $payload->description);
        $this->apply($part, $payload);

        $this->entityManager->persist($part);
        $this->entityManager->flush();

        return $this->toArray($part);
    }

    public function update(int $id, SparePartPayload $payload): array
    {
        $part = $this->find($id);
        $this->assertUnique($payload->internalCode, $payload->partCode, $id);

        $part->setInternalCode($payload->internalCode);
        $part->setPartCode($payload->partCode);
        $part->setDescription($payload->description);
        $this->apply($part, $payload);

        $this->entityManager->flush();

        return $this->toArray($part);
    }

    public function delete(int $id): void
    {
        $part = $this->find($id);
        if ($part->getStock() > 0) {
            throw new ConflictHttpException('No puede eliminarse un repuesto con stock; realice primero un ajuste a cero.');
        }
        $part->markDeleted();
        $part->setActive(false);
        $this->entityManager->flush();
    }

    /** Ajuste manual de stock → movimiento AJUSTE en Kardex. */
    public function adjust(int $id, AdjustmentPayload $payload): array
    {
        $part = $this->find($id);
        $this->stockService->registerMovement(
            $part,
            'AJUSTE',
            $payload->quantity,
            null,
            'AJUSTE manual',
            $payload->reason,
        );

        return $this->toArray($part);
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function kardex(int $id, int $page, int $perPage): array
    {
        $part = $this->find($id);
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $qb = $this->kardexRepository->createQueryBuilder('k')
            ->andWhere('k.sparePart = :part')->setParameter('part', $part)
            ->orderBy('k.createdAt', 'DESC')->addOrderBy('k.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->stockService->toArray(...), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    private function find(int $id): SparePart
    {
        return $this->partRepository->find($id)
            ?? throw new NotFoundHttpException('Repuesto no encontrado.');
    }

    private function assertUnique(string $internalCode, string $partCode, ?int $exceptId): void
    {
        $existing = $this->partRepository->findOneByInternalCode($internalCode);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El código interno %s ya existe.', strtoupper($internalCode)));
        }
        $existing = $this->partRepository->findOneByPartCode($partCode);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El código de repuesto %s ya existe: %s.', strtoupper($partCode), $existing->getDescription()));
        }
    }

    private function apply(SparePart $part, SparePartPayload $payload): void
    {
        $part->setBarcode($payload->barcode ?: null);
        $part->setUnitOfMeasure($payload->unitOfMeasure ?: 'UNIDAD');
        $part->setBrand($payload->brandId !== null ? $this->findCatalog($payload->brandId, 'brands') : null);
        $part->setCategory($payload->categoryId !== null ? $this->findCatalog($payload->categoryId, 'categories') : null);
        $part->setMinStock($payload->minStock);
        $part->setMaxStock($payload->maxStock);
        $part->setPurchasePrice($payload->purchasePrice !== null ? number_format($payload->purchasePrice, 2, '.', '') : null);
        $part->setSalePrice($payload->salePrice !== null ? number_format($payload->salePrice, 2, '.', '') : null);
        $part->setLocation($payload->location);
        $part->setActive($payload->isActive);

        $part->clearCompatibleModels();
        foreach (array_unique($payload->compatibleModelIds) as $modelId) {
            $model = $this->modelRepository->find($modelId);
            if ($model !== null && !$model->isDeleted()) {
                $part->addCompatibleModel($model);
            }
        }
    }

    private function findCatalog(int $id, string $type): ?\App\Module\Catalog\Entity\CatalogItem
    {
        $item = $this->catalogRepository->find($id);

        return ($item !== null && $item->getType() === $type) ? $item : null;
    }

    public function toArray(SparePart $part): array
    {
        return [
            'id' => $part->getId(),
            'internalCode' => $part->getInternalCode(),
            'partCode' => $part->getPartCode(),
            'barcode' => $part->getBarcode(),
            'description' => $part->getDescription(),
            'brandId' => $part->getBrand()?->getId(),
            'brandName' => $part->getBrand()?->getName(),
            'categoryId' => $part->getCategory()?->getId(),
            'categoryName' => $part->getCategory()?->getName(),
            'unitOfMeasure' => $part->getUnitOfMeasure(),
            'compatibleModelIds' => array_map(static fn ($m) => $m->getId(), $part->getCompatibleModels()->toArray()),
            'compatibleModelNames' => array_map(static fn ($m) => $m->getFullName(), $part->getCompatibleModels()->toArray()),
            'stock' => $part->getStock(),
            'minStock' => $part->getMinStock(),
            'maxStock' => $part->getMaxStock(),
            'purchasePrice' => $part->getPurchasePrice(),
            'salePrice' => $part->getSalePrice(),
            'location' => $part->getLocation(),
            'lastPurchaseAt' => $part->getLastPurchaseAt()?->format(\DateTimeInterface::ATOM),
            'isLowStock' => $part->isLowStock(),
            'isOutOfStock' => $part->isOutOfStock(),
            'isActive' => $part->isActive(),
        ];
    }
}
