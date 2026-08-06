<?php

declare(strict_types=1);

namespace App\Module\Pricing\Repository;

use App\Module\Pricing\Entity\PriceList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceList>
 */
final class PriceListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceList::class);
    }

    public function findOneByCode(string $code): ?PriceList
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findDefault(): ?PriceList
    {
        return $this->findOneBy(['isDefault' => true, 'isActive' => true]);
    }
}
