<?php

declare(strict_types=1);

namespace App\Module\Dispatch\Repository;

use App\Module\Dispatch\Entity\DispatchGuide;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DispatchGuide>
 */
class DispatchGuideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DispatchGuide::class);
    }

    /** Siguiente correlativo para la serie (máximo existente + 1). */
    public function nextCorrelative(string $series): int
    {
        $max = (int) $this->createQueryBuilder('g')
            ->select('COALESCE(MAX(g.correlative), 0)')
            ->andWhere('g.series = :s')->setParameter('s', $series)
            ->getQuery()
            ->getSingleScalarResult();

        return $max + 1;
    }
}
