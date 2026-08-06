<?php

declare(strict_types=1);

namespace App\Module\CashRegister\Repository;

use App\Module\CashRegister\Entity\CashSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashSession>
 */
class CashSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashSession::class);
    }

    public function findOpenSession(): ?CashSession
    {
        return $this->findOneBy(['status' => 'ABIERTA']);
    }

    public function nextSequence(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id) + 1')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
