<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Repository;

use App\Module\Invoicing\Entity\ElectronicDocument;
use App\Module\Sales\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ElectronicDocument>
 */
class ElectronicDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ElectronicDocument::class);
    }

    /** ¿La venta ya tiene un comprobante vigente (no rechazado)? */
    public function findActiveForSale(Sale $sale): ?ElectronicDocument
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.sale = :sale')->setParameter('sale', $sale)
            ->andWhere("d.status != 'RECHAZADO'")
            ->andWhere("d.docType IN ('01', '03')")
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
