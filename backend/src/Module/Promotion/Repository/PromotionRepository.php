<?php

declare(strict_types=1);

namespace App\Module\Promotion\Repository;

use App\Module\Promotion\Entity\Promotion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Promotion>
 */
final class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    public function findOneByCode(string $code): ?Promotion
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Promociones activas y vigentes en la fecha dada.
     *
     * @return list<Promotion>
     */
    public function findActiveOn(\DateTimeImmutable $date, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->andWhere('p.startDate <= :d')
            ->andWhere('p.endDate >= :d')
            ->setParameter('d', $date);

        if ($type !== null) {
            $qb->andWhere('p.type = :t')->setParameter('t', $type);
        }

        return $qb->getQuery()->getResult();
    }
}
