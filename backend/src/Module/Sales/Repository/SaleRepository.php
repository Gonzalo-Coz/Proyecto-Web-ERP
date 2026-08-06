<?php

declare(strict_types=1);

namespace App\Module\Sales\Repository;

use App\Module\Sales\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    public function nextSequence(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id) + 1')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
