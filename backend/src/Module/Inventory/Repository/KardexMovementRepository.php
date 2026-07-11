<?php

declare(strict_types=1);

namespace App\Module\Inventory\Repository;

use App\Module\Inventory\Entity\KardexMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KardexMovement>
 */
class KardexMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KardexMovement::class);
    }
}
