<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Repository;

use App\Module\Invoicing\Entity\DocumentSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentSeries>
 */
class DocumentSeriesRepository extends ServiceEntityRepository
{
    /** Series por defecto por tipo (configurables desde §17 en fase posterior). */
    private const DEFAULTS = ['01' => 'F001', '03' => 'B001', '07' => 'FC01', '08' => 'FD01'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentSeries::class);
    }

    /** Obtiene (o crea) la serie activa del tipo, con bloqueo de escritura. */
    public function lockActiveSeries(string $docType): DocumentSeries
    {
        $series = $this->createQueryBuilder('s')
            ->andWhere('s.docType = :t')->setParameter('t', $docType)
            ->andWhere('s.isActive = true')
            ->setMaxResults(1)
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if ($series === null) {
            $series = new DocumentSeries($docType, self::DEFAULTS[$docType] ?? 'X001');
            $this->getEntityManager()->persist($series);
            $this->getEntityManager()->flush();
            $this->getEntityManager()->lock($series, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        }

        return $series;
    }
}
