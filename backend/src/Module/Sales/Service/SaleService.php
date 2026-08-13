<?php

declare(strict_types=1);

namespace App\Module\Sales\Service;

use App\Module\CashRegister\Service\CashService;
use App\Module\Catalog\Repository\CatalogItemRepository;
use App\Module\Customer\Repository\CustomerRepository;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Inventory\Service\StockService;
use App\Module\Invoicing\Repository\ElectronicDocumentRepository;
use App\Module\Payment\Service\PaymentGatewayService;
use App\Module\Motorcycle\Repository\MotorcycleUnitRepository;
use App\Module\Sales\Dto\SalePayload;
use App\Module\Sales\Entity\Sale;
use App\Module\Sales\Entity\SaleItem;
use App\Module\Sales\Entity\SalePayment;
use App\Module\Sales\Repository\SaleRepository;
use App\Shared\Settings\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Núcleo comercial (§12). Completar una venta es UNA transacción que:
 * valida stock y unidades → descuenta inventario (Kardex VENTA) →
 * marca unidades VENDIDAS → deja la venta lista para cobros en Caja.
 */
final class SaleService
{
    private const SORTABLE = ['saleNumber', 'saleDate', 'total', 'status', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SaleRepository $saleRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly MotorcycleUnitRepository $unitRepository,
        private readonly CatalogItemRepository $catalogRepository,
        private readonly StockService $stockService,
        private readonly CashService $cashService,
        private readonly Security $security,
        private readonly SettingsService $settings,
        private readonly ElectronicDocumentRepository $documentRepository,
        private readonly PaymentGatewayService $paymentGateway,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction, string $status, int $customerId): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'saleDate';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        $qb = $this->saleRepository->createQueryBuilder('v')
            ->join('v.customer', 'c')->addSelect('c')
            ->orderBy('v.'.$sort, $direction)->addOrderBy('v.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(v.saleNumber) LIKE :s OR LOWER(c.name) LIKE :s OR c.documentNumber LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if ($status !== '' && in_array($status, Sale::STATUSES, true)) {
            $qb->andWhere('v.status = :st')->setParameter('st', $status);
        }
        if ($customerId > 0) {
            $qb->andWhere('c.id = :cid')->setParameter('cid', $customerId);
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map(fn (Sale $s) => $this->toArray($s, false), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id), true);
    }

    /** Crea cotización, o venta directa completada si complete=true. */
    public function create(SalePayload $payload): array
    {
        $customer = $this->customerRepository->find($payload->customerId)
            ?? throw new UnprocessableEntityHttpException('Cliente no encontrado.');
        if (!$customer->isActive()) {
            throw new UnprocessableEntityHttpException('El cliente está inactivo.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($payload, $customer): array {
            $sale = new Sale($customer, $this->username(), new \DateTimeImmutable($payload->saleDate));
            $sale->assignNumber($this->saleRepository->nextSequence());
            $sale->setNotes($payload->notes);

            $this->applyLines($sale, $payload);

            $this->entityManager->persist($sale);
            $this->entityManager->flush();

            if ($payload->complete) {
                $this->executeCompletion($sale);
                $this->entityManager->flush();
            }

            return $this->toArray($sale, true);
        });
    }

    /** Cotización → Reserva: bloquea las unidades de moto (estado RESERVADA). */
    public function reserve(int $id, ?string $expiresAt): array
    {
        $sale = $this->find($id);
        if ($sale->getStatus() !== 'COTIZACION') {
            throw new ConflictHttpException('Solo una cotización puede convertirse en reserva.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($sale, $expiresAt): array {
            foreach ($sale->getItems() as $item) {
                $unit = $item->getMotorcycleUnit();
                if ($item->getItemType() === 'MOTORCYCLE_UNIT' && $unit !== null) {
                    if ($unit->getStatus() !== 'DISPONIBLE') {
                        throw new ConflictHttpException(sprintf('La unidad %s no está disponible (estado %s).', $unit->getInternalCode(), $unit->getStatus()));
                    }
                    $unit->setStatus('RESERVADA');
                }
            }
            $sale->setStatus('RESERVA');
            $sale->setReservationExpiresAt($expiresAt !== null ? new \DateTimeImmutable($expiresAt) : null);
            $this->entityManager->flush();

            return $this->toArray($sale, true);
        });
    }

    /** Completa la venta (desde COTIZACION o RESERVA): efectos §12. */
    public function complete(int $id): array
    {
        $sale = $this->find($id);
        if (!in_array($sale->getStatus(), ['COTIZACION', 'RESERVA'], true)) {
            throw new ConflictHttpException('Solo cotizaciones o reservas pueden completarse.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($sale): array {
            $this->executeCompletion($sale);
            $this->entityManager->flush();

            return $this->toArray($sale, true);
        });
    }

    /** Registra un cobro: actualiza CxC y genera INGRESO en Caja (requiere caja abierta). */
    public function addPayment(int $id, float $amount, ?int $paymentMethodId, ?string $reference): array
    {
        $sale = $this->find($id);
        if (!in_array($sale->getStatus(), ['RESERVA', 'COMPLETADA'], true)) {
            throw new ConflictHttpException('Solo reservas (adelantos) o ventas completadas admiten cobros.');
        }
        if ($amount <= 0) {
            throw new UnprocessableEntityHttpException('El monto debe ser mayor a cero.');
        }
        $balance = (float) $sale->getBalance();
        if ($amount > $balance + 0.005) {
            throw new UnprocessableEntityHttpException(sprintf('El cobro (%.2f) excede el saldo pendiente (%.2f).', $amount, $balance));
        }

        return $this->entityManager->wrapInTransaction(function () use ($sale, $amount, $paymentMethodId, $reference): array {
            $paymentMethod = null;
            if ($paymentMethodId !== null) {
                $paymentMethod = $this->catalogRepository->find($paymentMethodId);
                if ($paymentMethod === null || $paymentMethod->getType() !== 'payment_methods') {
                    throw new UnprocessableEntityHttpException('Medio de pago inválido.');
                }
            }

            // Regla §13/§19: el cobro genera movimiento de caja (exige caja abierta)
            $this->cashService->registerMovement(
                'INGRESO',
                $amount,
                $paymentMethodId,
                sprintf('Cobro venta %s — %s', $sale->getSaleNumber(), $sale->getCustomer()->getName()),
                $sale->getSaleNumber(),
            );

            $payment = new SalePayment($sale, $amount, $paymentMethod, $reference, $this->username());
            $this->entityManager->persist($payment);
            $sale->registerPaidAmount($amount);
            $this->entityManager->flush();

            // Registro automático en el módulo de pagos (sin doble captura).
            $this->paymentGateway->recordFromSale(
                (int) $sale->getId(),
                $sale->getSaleNumber(),
                $sale->getCustomer()->getName(),
                $this->mapPaymentMethod($paymentMethod?->getName()),
                $amount,
            );

            return $this->toArray($sale, true);
        });
    }

    /** Traduce el nombre del medio de pago (catálogo) al método de la pasarela. */
    private function mapPaymentMethod(?string $name): string
    {
        $n = mb_strtoupper(trim((string) $name));

        return match (true) {
            str_contains($n, 'YAPE') => 'YAPE',
            str_contains($n, 'PLIN') => 'PLIN',
            str_contains($n, 'TARJETA') || str_contains($n, 'CARD') || str_contains($n, 'VISA') || str_contains($n, 'POS') => 'CARD',
            str_contains($n, 'TRANSFER') || str_contains($n, 'DEPOSITO') || str_contains($n, 'DEPÓSITO') || str_contains($n, 'BANCO') => 'TRANSFER',
            $n === '' || str_contains($n, 'EFECTIVO') || str_contains($n, 'CASH') => 'EFECTIVO',
            default => 'OTHER',
        };
    }

    /** Anulación: libera unidades y revierte stock si estaba completada. */
    public function cancel(int $id): array
    {
        $sale = $this->find($id);
        if ($sale->getStatus() === 'ANULADA') {
            throw new ConflictHttpException('La venta ya está anulada.');
        }
        if ((float) $sale->getPaidAmount() > 0) {
            throw new ConflictHttpException('Tiene cobros registrados: primero registra el egreso de devolución en Caja y ajusta los cobros.');
        }
        // Regla SUNAT: una venta con comprobante emitido no se anula libremente;
        // el comprobante es un documento legal y se revierte con una Nota de Crédito.
        $document = $this->documentRepository->findActiveForSale($sale);
        if ($document !== null) {
            throw new ConflictHttpException(sprintf(
                'La venta tiene un comprobante emitido (%s %s). No puede anularse: para revertirla emite una Nota de Crédito.',
                $document->getDocTypeName(),
                $document->getFullNumber(),
            ));
        }

        return $this->entityManager->wrapInTransaction(function () use ($sale): array {
            foreach ($sale->getItems() as $item) {
                $unit = $item->getMotorcycleUnit();
                if ($item->getItemType() === 'MOTORCYCLE_UNIT' && $unit !== null && in_array($unit->getStatus(), ['RESERVADA', 'VENDIDA'], true)) {
                    $unit->setStatus('DISPONIBLE');
                }
                if ($sale->getStatus() === 'COMPLETADA' && $item->getItemType() === 'SPARE_PART' && $item->getSparePart() !== null) {
                    $this->stockService->registerMovement(
                        $item->getSparePart(),
                        'DEVOLUCION',
                        $item->getQuantity(),
                        null,
                        sprintf('ANULACIÓN %s', $sale->getSaleNumber()),
                        'Reversión por anulación de venta',
                    );
                }
            }
            $sale->setStatus('ANULADA');
            $this->entityManager->flush();

            return $this->toArray($sale, true);
        });
    }

    /** Efectos de completar (§12): stock, Kardex, estado de unidades. */
    private function executeCompletion(Sale $sale): void
    {
        foreach ($sale->getItems() as $item) {
            if ($item->getItemType() === 'SPARE_PART' && $item->getSparePart() !== null) {
                // Regla §19: no vender con stock insuficiente (validado por StockService)
                $this->stockService->registerMovement(
                    $item->getSparePart(),
                    'VENTA',
                    -$item->getQuantity(),
                    null,
                    sprintf('VENTA %s', $sale->getSaleNumber()),
                    null,
                );
            }
            $unit = $item->getMotorcycleUnit();
            if ($item->getItemType() === 'MOTORCYCLE_UNIT' && $unit !== null) {
                // Regla §19: no vender motocicletas ya vendidas
                if (!in_array($unit->getStatus(), ['DISPONIBLE', 'RESERVADA'], true)) {
                    throw new ConflictHttpException(sprintf('La unidad %s no puede venderse (estado %s).', $unit->getInternalCode(), $unit->getStatus()));
                }
                $unit->setStatus('VENDIDA');
            }
        }

        $sale->markCompleted();
    }

    private function applyLines(Sale $sale, SalePayload $payload): void
    {
        $subtotal = 0.0;
        $seenUnits = [];

        foreach (array_values($payload->items) as $index => $line) {
            $position = $index + 1;
            $itemType = (string) ($line['itemType'] ?? '');
            $quantity = (int) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['unitPrice'] ?? 0);
            // Descuento ÚNICAMENTE porcentual por línea (admite decimales)
            $discountPercent = round((float) ($line['discountPercent'] ?? 0), 2);

            if ($quantity < 1 || $unitPrice < 0) {
                throw new UnprocessableEntityHttpException(sprintf('Línea %d: cantidad o precio inválidos.', $position));
            }
            if ($discountPercent < 0 || $discountPercent > 100) {
                throw new UnprocessableEntityHttpException(sprintf('Línea %d: el descuento debe estar entre 0%% y 100%%.', $position));
            }

            $gross = $quantity * $unitPrice;
            $discount = round($gross * $discountPercent / 100, 2);

            if ($itemType === 'SPARE_PART') {
                $part = $this->sparePartRepository->find((int) ($line['sparePartId'] ?? 0))
                    ?? throw new UnprocessableEntityHttpException(sprintf('Línea %d: repuesto no encontrado.', $position));
                if ($part->getStock() < $quantity) {
                    throw new ConflictHttpException(sprintf('Línea %d: stock insuficiente de %s (disponible %d).', $position, $part->getInternalCode(), $part->getStock()));
                }
                $item = new SaleItem($sale, 'SPARE_PART', $part->getDescription(), $quantity, $unitPrice, $discount, $discountPercent);
                $item->setSparePart($part);
            } elseif ($itemType === 'MOTORCYCLE_UNIT') {
                $unitId = (int) ($line['motorcycleUnitId'] ?? 0);
                if (isset($seenUnits[$unitId])) {
                    throw new UnprocessableEntityHttpException(sprintf('Línea %d: la unidad está repetida en la venta.', $position));
                }
                $seenUnits[$unitId] = true;
                $unit = $this->unitRepository->find($unitId)
                    ?? throw new UnprocessableEntityHttpException(sprintf('Línea %d: unidad no encontrada.', $position));
                if ($quantity !== 1) {
                    throw new UnprocessableEntityHttpException(sprintf('Línea %d: una unidad siempre tiene cantidad 1.', $position));
                }
                if ($unit->getStatus() !== 'DISPONIBLE') {
                    throw new ConflictHttpException(sprintf('Línea %d: la unidad %s no está disponible (estado %s).', $position, $unit->getInternalCode(), $unit->getStatus()));
                }
                $item = new SaleItem($sale, 'MOTORCYCLE_UNIT', $this->motorcycleDescription($unit), 1, $unitPrice, $discount, $discountPercent);
                $item->setMotorcycleUnit($unit);
            } elseif ($itemType === 'SERVICE') {
                $description = trim((string) ($line['description'] ?? ''));
                if ($description === '') {
                    throw new UnprocessableEntityHttpException(sprintf('Línea %d: el servicio requiere descripción.', $position));
                }
                $item = new SaleItem($sale, 'SERVICE', $description, $quantity, $unitPrice, $discount, $discountPercent);
            } else {
                throw new UnprocessableEntityHttpException(sprintf('Línea %d: tipo inválido.', $position));
            }

            $sale->addItem($item);
            $this->entityManager->persist($item);
            $subtotal += $gross - $discount;
        }

        // Precios CON IGV incluido (norma en tienda, Perú): la suma de las líneas
        // es el TOTAL a pagar; la base (op. gravada) y el IGV se calculan hacia
        // atrás con la tasa configurable (§23.10).
        $rate = $this->settings->igvRate();
        $sum = round($subtotal, 2);
        $sale->setIgvIncluded($payload->igvIncluded);
        $sale->setIgvExempt($payload->igvExempt);
        if ($payload->igvExempt) {
            // Amazonía (Ley 27037): operación EXONERADA, no se aplica IGV.
            $base = $sum;
            $igv = 0.0;
            $total = $sum;
        } elseif ($payload->igvIncluded) {
            // Zona local (Tingo María): el precio YA incluye IGV → se extrae.
            $base = round($sum / (1 + $rate), 2);
            $igv = round($sum - $base, 2);
            $total = $sum;
        } else {
            // Venta al exterior/fuera de zona: el precio es la base → IGV se agrega.
            $base = $sum;
            $igv = round($sum * $rate, 2);
            $total = round($sum + $igv, 2);
        }
        $sale->setTotals($base, $igv, $total);
    }

    private function find(int $id): Sale
    {
        return $this->saleRepository->find($id)
            ?? throw new NotFoundHttpException('Venta no encontrada.');
    }

    private function username(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? 'sistema';
    }

    /**
     * Descripción completa de una moto para el comprobante de venta de vehículos:
     * incluye VIN, motor, color y la DUA (obligatoria en vehículos importados).
     */
    private function motorcycleDescription(\App\Module\Motorcycle\Entity\MotorcycleUnit $unit): string
    {
        // Cada dato en su propia línea (etiquetado): se ve separado en el
        // comprobante, no como una sola cadena.
        $lines = [$unit->getModel()->getFullName()];
        $lines[] = 'Serie/VIN: '.$unit->getVin();
        if ($unit->getEngineNumber() !== null && $unit->getEngineNumber() !== '') {
            $lines[] = 'Motor: '.$unit->getEngineNumber();
        }
        $lines[] = 'Color: '.$unit->getColor();
        if ($unit->getDuaNumber() !== null) {
            $lines[] = 'DUA: '.$unit->getDuaNumber().($unit->getDuaItem() !== null ? '   Ítem: '.$unit->getDuaItem() : '');
        }

        return implode("\n", $lines);
    }

    public function toArray(Sale $s, bool $withDetail): array
    {
        $data = [
            'id' => $s->getId(),
            'saleNumber' => $s->getSaleNumber(),
            'saleDate' => $s->getSaleDate()->format('Y-m-d'),
            'customerId' => $s->getCustomer()->getId(),
            'customerName' => $s->getCustomer()->getName(),
            'customerDocument' => $s->getCustomer()->getDocumentType().' '.$s->getCustomer()->getDocumentNumber(),
            'seller' => $s->getSeller(),
            'status' => $s->getStatus(),
            'subtotal' => $s->getSubtotal(),
            'igv' => $s->getIgv(),
            'total' => $s->getTotal(),
            'igvIncluded' => $s->isIgvIncluded(),
            'igvExempt' => $s->isIgvExempt(),
            'globalDiscount' => $s->getGlobalDiscount(),
            'totalDiscount' => $s->getTotalDiscount(),
            'discountAuthorizedBy' => $s->getDiscountAuthorizedBy(),
            'discountAuthorizedAt' => $s->getDiscountAuthorizedAt()?->format(\DateTimeInterface::ATOM),
            'paidAmount' => $s->getPaidAmount(),
            'balance' => $s->getBalance(),
            'paymentStatus' => $s->getPaymentStatus(),
            'reservationExpiresAt' => $s->getReservationExpiresAt()?->format('Y-m-d'),
            'completedAt' => $s->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'notes' => $s->getNotes(),
        ];

        if ($withDetail) {
            $data['items'] = array_map(static fn (SaleItem $i) => [
                'id' => $i->getId(),
                'itemType' => $i->getItemType(),
                'sparePartId' => $i->getSparePart()?->getId(),
                'motorcycleUnitId' => $i->getMotorcycleUnit()?->getId(),
                'description' => $i->getDescription(),
                'quantity' => $i->getQuantity(),
                'unitPrice' => $i->getUnitPrice(),
                'discount' => $i->getDiscount(),
                'discountPercent' => $i->getDiscountPercent(),
                'lineTotal' => $i->getLineTotal(),
            ], $s->getItems()->toArray());

            $data['payments'] = array_map(static fn (SalePayment $p) => [
                'id' => $p->getId(),
                'amount' => $p->getAmount(),
                'paymentMethodName' => $p->getPaymentMethod()?->getName() ?? 'Efectivo',
                'reference' => $p->getReference(),
                'username' => $p->getUsername(),
                'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], $s->getPayments()->toArray());

            // Para imprimir la cotización (copia al cliente).
            $data['customerAddress'] = $s->getCustomer()->getAddress();
            $data['igvRate'] = $this->settings->igvRate() * 100;
            $data['company'] = [
                'name' => $this->settings->get('company.name') ?? '',
                'ruc' => $this->settings->get('company.ruc') ?? '',
                'address' => $this->settings->get('company.address') ?? '',
                'phone' => $this->settings->get('company.phone') ?? '',
                'email' => $this->settings->get('company.email') ?? '',
            ];
        }

        return $data;
    }
}
