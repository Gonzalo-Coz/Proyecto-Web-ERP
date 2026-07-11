<?php

declare(strict_types=1);

namespace App\Module\Supplier\Repository;

use App\Module\Supplier\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supplier>
 */
class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    public function findOneByRuc(string $ruc): ?Supplier
    {
        return $this->findOneBy(['ruc' => $ruc]);
    }
}
