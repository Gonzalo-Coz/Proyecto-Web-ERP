<?php

declare(strict_types=1);

namespace App\Module\Lookup\Repository;

use App\Module\Lookup\Entity\VehicleLookup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VehicleLookup>
 */
class VehicleLookupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VehicleLookup::class);
    }

    public function findOneByPlaca(string $placa): ?VehicleLookup
    {
        return $this->findOneBy(['placa' => strtoupper($placa)]);
    }
}
