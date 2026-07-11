<?php

declare(strict_types=1);

namespace App\Module\Inventory\Service;

use App\Module\Inventory\Entity\KardexMovement;
use App\Module\Inventory\Entity\SparePart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * ÚNICO punto de entrada para modificar stock (§10, §23.15).
 * Aplica el cambio y genera el Kardex en la misma transacción.
 * Los módulos de Compras, Ventas y Taller lo invocarán en sus operaciones.
 */
final class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    /**
     * Registra un movimiento de inventario de forma transaccional.
     *
     * @param int $quantity Con signo: positivo entra, negativo sale.
     */
    public function registerMovement(
        SparePart $sparePart,
        string $movementType,
        int $quantity,
        ?float $unitCost = null,
        ?string $reference = null,
        ?string $notes = null,
    ): KardexMovement {
        if (!in_array($movementType, KardexMovement::TYPES, true)) {
            throw new UnprocessableEntityHttpException('Tipo de movimiento de Kardex inválido.');
        }
        if ($quantity === 0) {
            throw new UnprocessableEntityHttpException('La cantidad del movimiento no puede ser cero.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($sparePart, $movementType, $quantity, $unitCost, $reference, $notes): KardexMovement {
            // Bloqueo pesimista: evita sobreventa con operaciones concurrentes.
            $this->entityManager->lock($sparePart, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

            try {
                $sparePart->applyStockChange($quantity);
            } catch (\DomainException $e) {
                throw new ConflictHttpException($e->getMessage());
            }

            if ($movementType === 'COMPRA') {
                $sparePart->setLastPurchaseAt(new \DateTimeImmutable());
                if ($unitCost !== null) {
                    $sparePart->setPurchasePrice(number_format($unitCost, 2, '.', ''));
                }
            }

            $movement = new KardexMovement(
                $sparePart,
                $movementType,
                $quantity,
                $sparePart->getStock(),
                $unitCost !== null ? number_format($unitCost, 2, '.', '') : null,
                $reference,
                $notes,
                $this->security->getUser()?->getUserIdentifier(),
            );

            $this->entityManager->persist($movement);
            $this->entityManager->flush();

            return $movement;
        });
    }

    public function toArray(KardexMovement $m): array
    {
        return [
            'id' => $m->getId(),
            'movementType' => $m->getMovementType(),
            'quantity' => $m->getQuantity(),
            'unitCost' => $m->getUnitCost(),
            'balanceAfter' => $m->getBalanceAfter(),
            'reference' => $m->getReference(),
            'notes' => $m->getNotes(),
            'username' => $m->getUsername(),
            'createdAt' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
