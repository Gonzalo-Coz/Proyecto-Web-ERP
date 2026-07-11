<?php

declare(strict_types=1);

namespace App\Module\Inventory\Repository;

use App\Module\Inventory\Entity\SparePart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SparePart>
 */
class SparePartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SparePart::class);
    }

    public function findOneByInternalCode(string $code): ?SparePart
    {
        return $this->findOneBy(['internalCode' => strtoupper($code)]);
    }

    public function findOneByPartCode(string $code): ?SparePart
    {
        return $this->findOneBy(['partCode' => strtoupper($code)]);
    }
}
