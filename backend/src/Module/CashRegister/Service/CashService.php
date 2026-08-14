<?php

declare(strict_types=1);

namespace App\Module\CashRegister\Service;

use App\Module\CashRegister\Entity\CashMovement;
use App\Module\CashRegister\Entity\CashSession;
use App\Module\CashRegister\Repository\CashMovementRepository;
use App\Module\CashRegister\Repository\CashSessionRepository;
use App\Module\Catalog\Repository\CatalogItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Lógica de Caja (§13). registerMovement es el punto que usará Ventas
 * para registrar cobros automáticamente.
 */
final class CashService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CashSessionRepository $sessionRepository,
        private readonly CashMovementRepository $movementRepository,
        private readonly CatalogItemRepository $catalogRepository,
        private readonly Security $security,
    ) {
    }

    public function open(float $openingAmount, ?string $notes): array
    {
        if ($openingAmount < 0) {
            throw new UnprocessableEntityHttpException('El monto de apertura no puede ser negativo.');
        }
        if ($this->sessionRepository->findOpenSession() !== null) {
            throw new ConflictHttpException('Ya existe una caja abierta; ciérrala antes de abrir otra.');
        }

        $session = new CashSession($this->username(), $openingAmount);
        $session->assignNumber($this->sessionRepository->nextSequence());
        $session->setNotes($notes);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $this->sessionToArray($session);
    }

    public function close(int $sessionId, float $countedAmount, ?string $notes): array
    {
        $session = $this->sessionRepository->find($sessionId)
            ?? throw new NotFoundHttpException('Sesión de caja no encontrada.');
        if (!$session->isOpen()) {
            throw new ConflictHttpException('La sesión ya está cerrada.');
        }

        $totals = $this->movementRepository->totalsForSession($session);
        $expected = (float) $session->getOpeningAmount() + $totals['cashIncome'] - $totals['cashExpense'];

        $session->close($this->username(), $countedAmount, $expected, $notes);
        $this->entityManager->flush();

        return $this->sessionToArray($session);
    }

    /** ¿Hay una caja abierta actualmente? */
    public function hasOpenSession(): bool
    {
        return $this->sessionRepository->findOpenSession() !== null;
    }

    /**
     * Registra un movimiento. Regla §13: sin caja abierta no hay movimientos.
     * Ventas (Fase 6) llamará este método con la referencia del comprobante.
     */
    public function registerMovement(
        string $movementType,
        float $amount,
        ?int $paymentMethodId,
        string $concept,
        ?string $reference = null,
    ): CashMovement {
        if (!in_array($movementType, CashMovement::TYPES, true)) {
            throw new UnprocessableEntityHttpException('Tipo de movimiento inválido (INGRESO o EGRESO).');
        }
        if ($amount <= 0) {
            throw new UnprocessableEntityHttpException('El monto debe ser mayor a cero.');
        }
        if (trim($concept) === '') {
            throw new UnprocessableEntityHttpException('El concepto es obligatorio.');
        }

        $session = $this->sessionRepository->findOpenSession()
            ?? throw new ConflictHttpException('No hay caja abierta: apertura requerida antes de registrar movimientos.');

        $paymentMethod = null;
        if ($paymentMethodId !== null) {
            $paymentMethod = $this->catalogRepository->find($paymentMethodId);
            if ($paymentMethod === null || $paymentMethod->getType() !== 'payment_methods') {
                throw new UnprocessableEntityHttpException('Medio de pago inválido.');
            }
        }

        $movement = new CashMovement($session, $movementType, $amount, $paymentMethod, trim($concept), $reference ?? 'MANUAL', $this->username());
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return $movement;
    }

    /** Estado actual: sesión abierta (o null) con totales en vivo. */
    public function current(): array
    {
        $session = $this->sessionRepository->findOpenSession();
        if ($session === null) {
            return ['session' => null];
        }

        return ['session' => $this->sessionToArray($session)];
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function listSessions(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $qb = $this->sessionRepository->createQueryBuilder('s')
            ->orderBy('s.openedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->sessionToArray(...), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function movements(int $sessionId): array
    {
        $session = $this->sessionRepository->find($sessionId)
            ?? throw new NotFoundHttpException('Sesión de caja no encontrada.');

        $movements = $this->movementRepository->findBy(['session' => $session], ['createdAt' => 'DESC']);

        return array_map(static fn (CashMovement $m) => [
            'id' => $m->getId(),
            'movementType' => $m->getMovementType(),
            'amount' => $m->getAmount(),
            'paymentMethodName' => $m->getPaymentMethod()?->getName() ?? 'Efectivo',
            'concept' => $m->getConcept(),
            'reference' => $m->getReference(),
            'username' => $m->getUsername(),
            'createdAt' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $movements);
    }

    private function username(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? 'sistema';
    }

    public function sessionToArray(CashSession $s): array
    {
        $totals = $this->movementRepository->totalsForSession($s);
        $expectedCash = (float) $s->getOpeningAmount() + $totals['cashIncome'] - $totals['cashExpense'];

        return [
            'id' => $s->getId(),
            'sessionNumber' => $s->getSessionNumber(),
            'openedBy' => $s->getOpenedBy(),
            'openedAt' => $s->getOpenedAt()->format(\DateTimeInterface::ATOM),
            'openingAmount' => $s->getOpeningAmount(),
            'closedBy' => $s->getClosedBy(),
            'closedAt' => $s->getClosedAt()?->format(\DateTimeInterface::ATOM),
            'countedAmount' => $s->getCountedAmount(),
            'expectedAmount' => $s->getExpectedAmount(),
            'difference' => $s->getDifference(),
            'status' => $s->getStatus(),
            'notes' => $s->getNotes(),
            'totalIncome' => number_format($totals['income'], 2, '.', ''),
            'totalExpense' => number_format($totals['expense'], 2, '.', ''),
            'liveExpectedCash' => number_format($expectedCash, 2, '.', ''),
        ];
    }
}
