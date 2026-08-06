<?php

declare(strict_types=1);

namespace App\Module\Payment\Service;

use App\Module\Payment\Dto\PaymentTransactionPayload;
use App\Module\Payment\Entity\PaymentTransaction;
use App\Module\Payment\Provider\PaymentGatewayInterface;
use App\Module\Payment\Repository\PaymentTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Registro y validación de transacciones de pasarela (Adición A6).
 *
 * Depende de PaymentGatewayInterface (adaptador activo por alias), nunca de una
 * pasarela concreta. No modifica Ventas ni Caja: es un registro estructurado;
 * la conciliación con la venta se hará al integrar una pasarela real.
 */
final class PaymentGatewayService
{
    private const SORTABLE = ['createdAt', 'amount', 'status', 'method'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentTransactionRepository $repository,
        private readonly PaymentGatewayInterface $gateway,
        private readonly Security $security,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $status, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'createdAt';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        $qb = $this->repository->createQueryBuilder('t')
            ->orderBy('t.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(t.operationNumber) LIKE :s OR LOWER(t.saleNumber) LIKE :s OR LOWER(t.customerLabel) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if (in_array($status, PaymentTransaction::STATUSES, true)) {
            $qb->andWhere('t.status = :st')->setParameter('st', $status);
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->toArray(...), iterator_to_array($paginator, false)),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function register(PaymentTransactionPayload $payload): array
    {
        $tx = new PaymentTransaction($payload->method, number_format($payload->amount, 2, '.', ''), $this->gateway->name());
        $tx->setOperationNumber($payload->operationNumber);
        $tx->setSaleId($payload->saleId);
        $tx->setSaleNumber($payload->saleNumber);
        $tx->setCustomerLabel($payload->customerLabel);
        $tx->setNotes($payload->notes);
        $tx->setCreatedBy($this->security->getUser()?->getUserIdentifier());

        // Procesa por la pasarela activa (v1: manual → PENDING).
        $result = $this->gateway->authorize($tx);
        $tx->setStatus($result->status);
        if ($result->operationNumber !== null) {
            $tx->setOperationNumber($result->operationNumber);
        }
        $tx->setResponsePayload($result->raw + ['message' => $result->message]);

        $this->entityManager->persist($tx);
        $this->entityManager->flush();

        return $this->toArray($tx);
    }

    /**
     * Registra automáticamente el pago de un cobro de venta ya recibido (APPROVED),
     * para que el módulo de pagos quede en sync con las ventas sin doble captura.
     */
    public function recordFromSale(int $saleId, string $saleNumber, ?string $customerLabel, string $method, float $amount): void
    {
        $method = in_array($method, PaymentTransaction::METHODS, true) ? $method : 'OTHER';
        $tx = new PaymentTransaction($method, number_format($amount, 2, '.', ''), $this->gateway->name());
        $tx->setSaleId($saleId);
        $tx->setSaleNumber($saleNumber);
        $tx->setCustomerLabel($customerLabel);
        $tx->setStatus('APPROVED'); // es un cobro real ya confirmado en la venta
        $tx->setCreatedBy($this->security->getUser()?->getUserIdentifier());
        $tx->setNotes('Generado automáticamente desde el cobro de la venta.');
        $this->entityManager->persist($tx);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    public function validate(int $id, bool $approve): array
    {
        $tx = $this->find($id);
        if ($tx->getStatus() !== 'PENDING') {
            throw new ConflictHttpException('Solo se pueden validar transacciones pendientes.');
        }
        $tx->setStatus($approve ? 'APPROVED' : 'REJECTED');
        $tx->setValidatedBy($this->security->getUser()?->getUserIdentifier());
        $tx->setValidatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->toArray($tx);
    }

    private function find(int $id): PaymentTransaction
    {
        return $this->repository->find($id)
            ?? throw new NotFoundHttpException('Transacción no encontrada.');
    }

    /** @return array<string, mixed> */
    private function toArray(PaymentTransaction $tx): array
    {
        return [
            'id' => $tx->getId(),
            'saleId' => $tx->getSaleId(),
            'saleNumber' => $tx->getSaleNumber(),
            'customerLabel' => $tx->getCustomerLabel(),
            'method' => $tx->getMethod(),
            'amount' => $tx->getAmount(),
            'currency' => $tx->getCurrency(),
            'status' => $tx->getStatus(),
            'gateway' => $tx->getGateway(),
            'operationNumber' => $tx->getOperationNumber(),
            'notes' => $tx->getNotes(),
            'createdBy' => $tx->getCreatedBy(),
            'validatedBy' => $tx->getValidatedBy(),
            'validatedAt' => $tx->getValidatedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $tx->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
