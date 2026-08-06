<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Repository;

use App\Module\Purchasing\Entity\Purchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Purchase>
 */
class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Purchase::class);
    }

    /** Siguiente número correlativo de compra (bloqueando la secuencia). */
    public function nextSequence(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id) + 1')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
