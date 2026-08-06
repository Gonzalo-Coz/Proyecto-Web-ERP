<?php

declare(strict_types=1);

namespace App\Module\CashRegister\Repository;

use App\Module\CashRegister\Entity\CashMovement;
use App\Module\CashRegister\Entity\CashSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashMovement>
 */
class CashMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashMovement::class);
    }

    /** @return array{income: float, expense: float, cashIncome: float, cashExpense: float} */
    public function totalsForSession(CashSession $session): array
    {
        $totals = ['income' => 0.0, 'expense' => 0.0, 'cashIncome' => 0.0, 'cashExpense' => 0.0];

        foreach ($this->findBy(['session' => $session]) as $movement) {
            $amount = (float) $movement->getAmount();
            if ($movement->getMovementType() === 'INGRESO') {
                $totals['income'] += $amount;
                if ($movement->isCash()) {
                    $totals['cashIncome'] += $amount;
                }
            } else {
                $totals['expense'] += $amount;
                if ($movement->isCash()) {
                    $totals['cashExpense'] += $amount;
                }
            }
        }

        return $totals;
    }
}
