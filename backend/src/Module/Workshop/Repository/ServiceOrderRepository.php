<?php

declare(strict_types=1);

namespace App\Module\Workshop\Repository;

use App\Module\Workshop\Entity\ServiceOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceOrder>
 */
class ServiceOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceOrder::class);
    }

    public function nextSequence(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id) + 1')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
