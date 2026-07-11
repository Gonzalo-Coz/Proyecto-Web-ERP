<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Repository;

use App\Module\Motorcycle\Entity\MotorcycleUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MotorcycleUnit>
 */
class MotorcycleUnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MotorcycleUnit::class);
    }

    public function findOneByVin(string $vin): ?MotorcycleUnit
    {
        // El VIN es único incluso entre eliminados: se consulta sin filtro soft delete.
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');
        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $this->findOneBy(['vin' => strtoupper($vin)]);
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }

    public function findOneByEngineNumber(string $engineNumber): ?MotorcycleUnit
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');
        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $this->findOneBy(['engineNumber' => strtoupper($engineNumber)]);
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }
}
