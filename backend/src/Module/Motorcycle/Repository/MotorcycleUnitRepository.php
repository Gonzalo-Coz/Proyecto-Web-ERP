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

    /** Siguiente código interno correlativo (M-00001, M-00002, …). */
    public function nextInternalCode(): string
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');
        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $rows = $this->createQueryBuilder('u')
                ->select('u.internalCode')
                ->where("u.internalCode LIKE 'M-%'")
                ->getQuery()
                ->getScalarResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }

        $max = 0;
        foreach ($rows as $r) {
            if (preg_match('/^M-(\d+)$/', (string) $r['internalCode'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'M-'.str_pad((string) ($max + 1), 5, '0', \STR_PAD_LEFT);
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
