<?php

declare(strict_types=1);

namespace App\Module\Workshop\Service;

use App\Module\Customer\Repository\CustomerRepository;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Inventory\Service\StockService;
use App\Module\Maintenance\Service\MaintenancePlanService;
use App\Module\Motorcycle\Repository\MotorcycleUnitRepository;
use App\Module\Sales\Dto\SalePayload;
use App\Module\Sales\Service\SaleService;
use App\Module\Workshop\Entity\ServiceOrder;
use App\Module\Workshop\Entity\ServiceOrderItem;
use App\Module\Workshop\Repository\ServiceOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Taller (§14): los repuestos usados descuentan inventario al agregarse
 * (Kardex TALLER) y se devuelven si se retiran de la orden. La facturación
 * genera una venta de servicio (módulo Ventas) por el total de la orden.
 */
final class WorkshopService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ServiceOrderRepository $orderRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly MotorcycleUnitRepository $unitRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly StockService $stockService,
        private readonly SaleService $saleService,
        private readonly MaintenancePlanService $maintenancePlans,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $status): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $qb = $this->orderRepository->createQueryBuilder('o')
            ->join('o.customer', 'c')->addSelect('c')
            ->orderBy('o.entryDate', 'DESC')->addOrderBy('o.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(o.orderNumber) LIKE :s OR LOWER(c.name) LIKE :s OR LOWER(o.plate) LIKE :s OR LOWER(o.motorcycleDescription) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if ($status !== '' && in_array($status, ServiceOrder::STATUSES, true)) {
            $qb->andWhere('o.status = :st')->setParameter('st', $status);
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map(fn (ServiceOrder $o) => $this->toArray($o, false), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id), true);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): array
    {
        $customer = $this->customerRepository->find((int) ($data['customerId'] ?? 0))
            ?? throw new UnprocessableEntityHttpException('Cliente no encontrado.');

        $unitId = isset($data['motorcycleUnitId']) && $data['motorcycleUnitId'] !== null ? (int) $data['motorcycleUnitId'] : null;
        $description = trim((string) ($data['motorcycleDescription'] ?? ''));

        if ($unitId === null && $description === '') {
            throw new UnprocessableEntityHttpException('Indica la unidad vendida por la empresa o la descripción de la motocicleta externa.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($data, $customer, $unitId, $description): array {
            $order = new ServiceOrder($customer, new \DateTimeImmutable((string) ($data['entryDate'] ?? 'today')));
            $order->assignNumber($this->orderRepository->nextSequence());

            if ($unitId !== null) {
                $unit = $this->unitRepository->find($unitId)
                    ?? throw new UnprocessableEntityHttpException('Unidad no encontrada.');
                $order->setMotorcycleUnit($unit);
                // Solo las unidades en stock propio cambian a EN_TALLER;
                // las vendidas (del cliente) conservan su estado.
                if ($unit->getStatus() === 'DISPONIBLE') {
                    $unit->setStatus('EN_TALLER');
                }
            }

            $order->setMotorcycleDescription($description !== '' ? $description : null);
            $order->setPlate(isset($data['plate']) ? (string) $data['plate'] : null);
            $order->setBroughtBy(isset($data['broughtBy']) ? (string) $data['broughtBy'] : null);
            $order->setMileage(isset($data['mileage']) && $data['mileage'] !== null ? (int) $data['mileage'] : null);
            $order->setEstimatedDate(isset($data['estimatedDate']) && $data['estimatedDate'] ? new \DateTimeImmutable((string) $data['estimatedDate']) : null);
            $order->setMechanicName(isset($data['mechanicName']) ? (string) $data['mechanicName'] : null);
            $order->setDiagnosis(isset($data['diagnosis']) ? (string) $data['diagnosis'] : null);
            $order->setNotes(isset($data['notes']) ? (string) $data['notes'] : null);

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $this->toArray($order, true);
        });
    }

    /** Agrega repuesto (descuenta stock, Kardex TALLER) o mano de obra. */
    public function addItem(int $orderId, array $data): array
    {
        $order = $this->find($orderId);
        $this->assertEditable($order);

        $itemType = (string) ($data['itemType'] ?? '');
        $quantity = (int) ($data['quantity'] ?? 0);
        $unitPrice = (float) ($data['unitPrice'] ?? 0);

        if (!in_array($itemType, ServiceOrderItem::TYPES, true) || $quantity < 1 || $unitPrice < 0) {
            throw new UnprocessableEntityHttpException('Tipo, cantidad o precio inválidos.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($order, $data, $itemType, $quantity, $unitPrice): array {
            if ($itemType === 'PART') {
                $part = $this->sparePartRepository->find((int) ($data['sparePartId'] ?? 0))
                    ?? throw new UnprocessableEntityHttpException('Repuesto no encontrado.');

                // §14: todo repuesto utilizado descuenta automáticamente el inventario
                $this->stockService->registerMovement(
                    $part,
                    'TALLER',
                    -$quantity,
                    null,
                    sprintf('TALLER %s', $order->getOrderNumber()),
                    null,
                );

                $item = new ServiceOrderItem($order, 'PART', $part->getDescription(), $quantity, $unitPrice);
                $item->setSparePart($part);
            } else {
                $description = trim((string) ($data['description'] ?? ''));
                if ($description === '') {
                    throw new UnprocessableEntityHttpException('La mano de obra requiere descripción.');
                }
                $item = new ServiceOrderItem($order, 'LABOR', $description, $quantity, $unitPrice);
            }

            $order->addItem($item);
            $this->entityManager->persist($item);
            $this->entityManager->flush();

            return $this->toArray($order, true);
        });
    }

    /**
     * Carga un plan de mantenimiento (modelo + kilometraje) en la orden:
     * agrega la mano de obra del plan y el kit de repuestos que estén cargados
     * en inventario con stock suficiente. Los que falten (sin ficha o sin stock)
     * se devuelven en "planWarnings" para que el mecánico decida.
     *
     * @param array<string, mixed> $data
     */
    public function applyMaintenancePlan(int $orderId, array $data): array
    {
        $order = $this->find($orderId);
        $this->assertEditable($order);

        $planId = (int) ($data['planId'] ?? 0);
        $km = (int) ($data['km'] ?? 0);
        if ($planId < 1 || $km < 1) {
            throw new UnprocessableEntityHttpException('Selecciona el modelo y el kilometraje del plan.');
        }

        $plan = $this->maintenancePlans->getService($planId, $km);

        // Selección opcional de repuestos a cargar (IDs de repuesto elegidos por
        // el mecánico). Si no se envía, se cargan todos los disponibles del kit.
        $selected = null;
        if (isset($data['sparePartIds']) && is_array($data['sparePartIds'])) {
            $selected = array_map('intval', $data['sparePartIds']);
        }

        return $this->entityManager->wrapInTransaction(function () use ($order, $plan, $selected): array {
            $warnings = [];

            // 1) Mano de obra del plan (una línea LABOR). Si el plan no trae costo
            //    (modelo sin tarifa), se agrega en 0 para que el mecánico lo defina.
            $laborCost = isset($plan['labor']['cost']) ? (float) $plan['labor']['cost'] : 0.0;
            $laborFree = !empty($plan['labor']['free']); // primeros 3 servicios: gratuitos
            $laborItem = new ServiceOrderItem(
                $order,
                'LABOR',
                sprintf(
                    'Mantenimiento %s - %s km (mano de obra%s)',
                    $plan['model'],
                    number_format((int) $plan['km'], 0, '.', ','),
                    $laborFree ? ', gratuito' : '',
                ),
                1,
                $laborFree ? 0.0 : round($laborCost, 2),
            );
            $laborItem->setFromPlan(true);
            $order->addItem($laborItem);
            $this->entityManager->persist($laborItem);

            // 2) Kit de repuestos: solo los que existen en inventario con stock.
            foreach ($plan['parts'] as $p) {
                $qty = max(1, (int) round((float) ($p['quantity'] ?? 1)));
                if (empty($p['inInventory']) || $p['sparePartId'] === null) {
                    $warnings[] = sprintf('%s %s: no está en inventario (agrégalo manualmente).', $p['code'], $p['description']);
                    continue;
                }
                // Si el mecánico eligió una selección, se omiten los no marcados.
                if ($selected !== null && !in_array((int) $p['sparePartId'], $selected, true)) {
                    continue;
                }
                $part = $this->sparePartRepository->find((int) $p['sparePartId']);
                if ($part === null) {
                    $warnings[] = sprintf('%s: repuesto no encontrado.', $p['code']);
                    continue;
                }
                if ($part->getStock() < $qty) {
                    $warnings[] = sprintf('%s %s: sin stock suficiente (disponible %d).', $part->getInternalCode(), $part->getDescription(), $part->getStock());
                    continue;
                }

                $this->stockService->registerMovement(
                    $part,
                    'TALLER',
                    -$qty,
                    null,
                    sprintf('TALLER %s', $order->getOrderNumber()),
                    null,
                );

                $item = new ServiceOrderItem($order, 'PART', $part->getDescription(), $qty, (float) ($part->getSalePrice() ?? 0));
                $item->setSparePart($part);
                $item->setFromPlan(true);
                $order->addItem($item);
                $this->entityManager->persist($item);
            }

            $this->entityManager->flush();

            $result = $this->toArray($order, true);
            $result['planWarnings'] = $warnings;

            return $result;
        });
    }

    /** Retira una línea; si era repuesto, devuelve el stock. */
    public function removeItem(int $orderId, int $itemId): array
    {
        $order = $this->find($orderId);
        $this->assertEditable($order);

        $item = null;
        foreach ($order->getItems() as $candidate) {
            if ($candidate->getId() === $itemId) {
                $item = $candidate;
                break;
            }
        }
        if ($item === null) {
            throw new NotFoundHttpException('Línea no encontrada en la orden.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($order, $item): array {
            if ($item->getItemType() === 'PART' && $item->getSparePart() !== null) {
                $this->stockService->registerMovement(
                    $item->getSparePart(),
                    'DEVOLUCION',
                    $item->getQuantity(),
                    null,
                    sprintf('TALLER %s (retiro)', $order->getOrderNumber()),
                    null,
                );
            }
            $order->removeItem($item);
            $this->entityManager->flush();

            return $this->toArray($order, true);
        });
    }

    public function changeStatus(int $orderId, string $status): array
    {
        $order = $this->find($orderId);
        if (!in_array($status, ServiceOrder::STATUSES, true)) {
            throw new UnprocessableEntityHttpException('Estado inválido.');
        }
        $this->assertEditable($order);

        if ($status === 'ENTREGADA') {
            $order->markDelivered();
            $unit = $order->getMotorcycleUnit();
            if ($unit !== null && $unit->getStatus() === 'EN_TALLER') {
                $unit->setStatus('DISPONIBLE');
            }
        } else {
            $order->setStatus($status);
        }

        $this->entityManager->flush();

        return $this->toArray($order, true);
    }

    /** Factura la orden: genera una venta de servicio COMPLETADA (§12/§14). */
    public function invoice(int $orderId): array
    {
        $order = $this->find($orderId);
        if ($order->getInvoiceSaleId() !== null) {
            throw new ConflictHttpException('La orden ya fue facturada.');
        }
        if ($order->getItems()->isEmpty()) {
            throw new ConflictHttpException('La orden no tiene trabajos ni repuestos que facturar.');
        }

        // Venta con UNA línea de servicio por el total: los repuestos ya
        // salieron del inventario vía Kardex TALLER (no se descuentan doble).
        $subtotal = $order->getTotal();
        $sale = $this->saleService->create(new SalePayload(
            customerId: $order->getCustomer()->getId(),
            saleDate: (new \DateTimeImmutable('today'))->format('Y-m-d'),
            items: [[
                'itemType' => 'SERVICE',
                'description' => sprintf('Servicio de taller %s', $order->getOrderNumber()),
                'quantity' => 1,
                'unitPrice' => round($subtotal, 2),
                'discount' => 0,
            ]],
            complete: true,
            notes: sprintf('Generada desde la orden de servicio %s', $order->getOrderNumber()),
        ));

        $order->setInvoiceSaleId((int) $sale['id']);
        $this->entityManager->flush();

        return $this->toArray($order, true);
    }

    private function assertEditable(ServiceOrder $order): void
    {
        if ($order->isDelivered()) {
            throw new ConflictHttpException('Una orden entregada no puede modificarse.');
        }
    }

    private function find(int $id): ServiceOrder
    {
        return $this->orderRepository->find($id)
            ?? throw new NotFoundHttpException('Orden de servicio no encontrada.');
    }

    public function toArray(ServiceOrder $o, bool $withItems): array
    {
        $data = [
            'id' => $o->getId(),
            'orderNumber' => $o->getOrderNumber(),
            'customerId' => $o->getCustomer()->getId(),
            'customerName' => $o->getCustomer()->getName(),
            'customerDocument' => $o->getCustomer()->getDocumentNumber(),
            'broughtBy' => $o->getBroughtBy(),
            'motorcycleUnitId' => $o->getMotorcycleUnit()?->getId(),
            'motorcycleLabel' => $o->getMotorcycleUnit() !== null
                ? sprintf('%s — VIN %s', $o->getMotorcycleUnit()->getModel()->getFullName(), $o->getMotorcycleUnit()->getVin())
                : $o->getMotorcycleDescription(),
            'plate' => $o->getPlate(),
            'mileage' => $o->getMileage(),
            'entryDate' => $o->getEntryDate()->format('Y-m-d'),
            'estimatedDate' => $o->getEstimatedDate()?->format('Y-m-d'),
            'deliveredAt' => $o->getDeliveredAt()?->format(\DateTimeInterface::ATOM),
            'mechanicName' => $o->getMechanicName(),
            'diagnosis' => $o->getDiagnosis(),
            'notes' => $o->getNotes(),
            'status' => $o->getStatus(),
            'invoiceSaleId' => $o->getInvoiceSaleId(),
            'total' => number_format($o->getTotal(), 2, '.', ''),
        ];

        if ($withItems) {
            $data['items'] = array_map(static fn (ServiceOrderItem $i) => [
                'id' => $i->getId(),
                'itemType' => $i->getItemType(),
                'sparePartId' => $i->getSparePart()?->getId(),
                'description' => $i->getDescription(),
                'quantity' => $i->getQuantity(),
                'unitPrice' => $i->getUnitPrice(),
                'lineTotal' => $i->getLineTotal(),
                'fromPlan' => $i->isFromPlan(),
            ], $o->getItems()->toArray());
        }

        return $data;
    }
}
