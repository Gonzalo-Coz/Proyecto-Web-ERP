<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Service;

use App\Module\Catalog\Repository\CatalogItemRepository;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Inventory\Service\StockService;
use App\Module\Motorcycle\Repository\MotorcycleUnitRepository;
use App\Module\Purchasing\Dto\PurchasePayload;
use App\Module\Purchasing\Entity\Purchase;
use App\Module\Purchasing\Entity\PurchaseItem;
use App\Module\Purchasing\Repository\PurchaseRepository;
use App\Module\Supplier\Repository\SupplierRepository;
use App\Shared\Settings\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PurchaseService
{
    private const SORTABLE = ['purchaseNumber', 'purchaseDate', 'total', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PurchaseRepository $purchaseRepository,
        private readonly SupplierRepository $supplierRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly MotorcycleUnitRepository $unitRepository,
        private readonly CatalogItemRepository $catalogRepository,
        private readonly StockService $stockService,
        private readonly SettingsService $settings,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'purchaseDate';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        $qb = $this->purchaseRepository->createQueryBuilder('p')
            ->join('p.supplier', 's')->addSelect('s')
            ->orderBy('p.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(p.purchaseNumber) LIKE :s OR LOWER(s.businessName) LIKE :s OR p.documentNumber LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map(fn (Purchase $p) => $this->toArray($p, false), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id), true);
    }

    /**
     * Registra la compra de forma TRANSACCIONAL (§23.15): cabecera, líneas,
     * incremento de stock + Kardex (repuestos) y datos de compra (unidades).
     */
    public function create(PurchasePayload $payload): array
    {
        $supplier = $this->supplierRepository->find($payload->supplierId)
            ?? throw new UnprocessableEntityHttpException('Proveedor no encontrado.');

        return $this->entityManager->wrapInTransaction(function () use ($payload, $supplier): array {
            $purchase = new Purchase($supplier, new \DateTimeImmutable($payload->purchaseDate), $payload->documentType);
            $purchase->assignNumber($this->purchaseRepository->nextSequence());
            $purchase->setSeries($payload->series);
            $purchase->setDocumentNumber($payload->documentNumber);
            $purchase->setNotes($payload->notes);

            if ($payload->paymentMethodId !== null) {
                $pm = $this->catalogRepository->find($payload->paymentMethodId);
                if ($pm !== null && $pm->getType() === 'payment_methods') {
                    $purchase->setPaymentMethod($pm);
                }
            }

            // Persistir la compra ANTES de las líneas: el registro de stock (Kardex)
            // hace flush, y sin esto Doctrine no cascadea la compra nueva de los ítems.
            $this->entityManager->persist($purchase);

            $subtotal = 0.0;
            foreach (array_values($payload->items) as $index => $line) {
                $subtotal += $this->processLine($purchase, $line, $index + 1);
            }

            $igv = round($subtotal * $this->settings->igvRate(), 2);
            $purchase->setTotals($subtotal, $igv, $subtotal + $igv);

            $this->entityManager->persist($purchase);
            $this->entityManager->flush();

            return $this->toArray($purchase, true);
        });
    }

    /** Anula la compra revirtiendo el inventario (movimiento DEVOLUCION). */
    public function cancel(int $id): array
    {
        $purchase = $this->find($id);
        if ($purchase->isCancelled()) {
            throw new ConflictHttpException('La compra ya está anulada.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($purchase): array {
            foreach ($purchase->getItems() as $item) {
                if ($item->getItemType() === 'SPARE_PART' && $item->getSparePart() !== null) {
                    // Falla con "stock insuficiente" si el stock comprado ya se consumió.
                    $this->stockService->registerMovement(
                        $item->getSparePart(),
                        'DEVOLUCION',
                        -$item->getQuantity(),
                        null,
                        sprintf('ANULACIÓN %s', $purchase->getPurchaseNumber()),
                        'Reversión por anulación de compra',
                    );
                }
                if ($item->getItemType() === 'MOTORCYCLE_UNIT' && $item->getMotorcycleUnit() !== null) {
                    if ($item->getMotorcycleUnit()->isSold()) {
                        throw new ConflictHttpException(sprintf(
                            'No puede anularse: la unidad %s ya fue vendida.',
                            $item->getMotorcycleUnit()->getInternalCode(),
                        ));
                    }
                }
            }

            $purchase->cancel();
            $this->entityManager->flush();

            return $this->toArray($purchase, true);
        });
    }

    /** @param array<string, mixed> $line */
    private function processLine(Purchase $purchase, array $line, int $position): float
    {
        $itemType = (string) ($line['itemType'] ?? '');
        $quantity = (int) ($line['quantity'] ?? 0);
        $unitPrice = (float) ($line['unitPrice'] ?? 0);
        $discount = (float) ($line['discount'] ?? 0);

        if ($quantity < 1 || $unitPrice < 0 || $discount < 0 || $discount > $quantity * $unitPrice) {
            throw new UnprocessableEntityHttpException(sprintf('Línea %d: cantidad, precio o descuento inválidos.', $position));
        }

        if ($itemType === 'SPARE_PART') {
            $part = $this->sparePartRepository->find((int) ($line['sparePartId'] ?? 0))
                ?? throw new UnprocessableEntityHttpException(sprintf('Línea %d: repuesto no encontrado.', $position));

            $item = new PurchaseItem($purchase, 'SPARE_PART', $part->getDescription(), $quantity, $unitPrice, $discount);
            $item->setSparePart($part);
            $purchase->addItem($item);
            $this->entityManager->persist($item);

            $this->stockService->registerMovement(
                $part,
                'COMPRA',
                $quantity,
                $unitPrice,
                sprintf('COMPRA %s', $purchase->getPurchaseNumber()),
                null,
            );
        } elseif ($itemType === 'MOTORCYCLE_UNIT') {
            $unit = $this->unitRepository->find((int) ($line['motorcycleUnitId'] ?? 0))
                ?? throw new UnprocessableEntityHttpException(sprintf('Línea %d: unidad de motocicleta no encontrada.', $position));
            if ($quantity !== 1) {
                throw new UnprocessableEntityHttpException(sprintf('Línea %d: una unidad de motocicleta siempre tiene cantidad 1.', $position));
            }
            if ($unit->isSold()) {
                throw new ConflictHttpException(sprintf('Línea %d: la unidad %s ya fue vendida.', $position, $unit->getInternalCode()));
            }

            $item = new PurchaseItem($purchase, 'MOTORCYCLE_UNIT', sprintf('%s — VIN %s', $unit->getModel()->getFullName(), $unit->getVin()), 1, $unitPrice, $discount);
            $item->setMotorcycleUnit($unit);
            $purchase->addItem($item);
            $this->entityManager->persist($item);

            // Actualiza el expediente de compra de la unidad (§9.2)
            $unit->setSupplier($purchase->getSupplier());
            $unit->setPurchaseDate($purchase->getPurchaseDate());
            $unit->setPurchasePrice(number_format($unitPrice, 2, '.', ''));
        } else {
            throw new UnprocessableEntityHttpException(sprintf('Línea %d: tipo inválido (SPARE_PART o MOTORCYCLE_UNIT).', $position));
        }

        return $quantity * $unitPrice - $discount;
    }

    private function find(int $id): Purchase
    {
        return $this->purchaseRepository->find($id)
            ?? throw new NotFoundHttpException('Compra no encontrada.');
    }

    public function toArray(Purchase $p, bool $withItems): array
    {
        $data = [
            'id' => $p->getId(),
            'purchaseNumber' => $p->getPurchaseNumber(),
            'purchaseDate' => $p->getPurchaseDate()->format('Y-m-d'),
            'supplierId' => $p->getSupplier()->getId(),
            'supplierName' => $p->getSupplier()->getBusinessName(),
            'documentType' => $p->getDocumentType(),
            'series' => $p->getSeries(),
            'documentNumber' => $p->getDocumentNumber(),
            'currency' => $p->getCurrency(),
            'paymentMethodName' => $p->getPaymentMethod()?->getName(),
            'subtotal' => $p->getSubtotal(),
            'igv' => $p->getIgv(),
            'total' => $p->getTotal(),
            'status' => $p->getStatus(),
            'notes' => $p->getNotes(),
        ];

        if ($withItems) {
            $data['items'] = array_map(static fn (PurchaseItem $i) => [
                'id' => $i->getId(),
                'itemType' => $i->getItemType(),
                'sparePartId' => $i->getSparePart()?->getId(),
                'motorcycleUnitId' => $i->getMotorcycleUnit()?->getId(),
                'description' => $i->getDescription(),
                'quantity' => $i->getQuantity(),
                'unitPrice' => $i->getUnitPrice(),
                'discount' => $i->getDiscount(),
                'lineTotal' => $i->getLineTotal(),
            ], $p->getItems()->toArray());
        }

        return $data;
    }
}
