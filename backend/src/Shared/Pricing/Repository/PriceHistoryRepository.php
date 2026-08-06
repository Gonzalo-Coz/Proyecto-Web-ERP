<?php

declare(strict_types=1);

namespace App\Shared\Pricing\Repository;

use App\Shared\Pricing\Entity\PriceHistoryEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceHistoryEntry>
 */
final class PriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceHistoryEntry::class);
    }
}
