<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Repository;

use App\Module\Motorcycle\Entity\MotorcycleModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MotorcycleModel>
 */
class MotorcycleModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MotorcycleModel::class);
    }
}
