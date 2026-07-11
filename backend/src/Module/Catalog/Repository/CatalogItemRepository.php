<?php

declare(strict_types=1);

namespace App\Module\Catalog\Repository;

use App\Module\Catalog\Entity\CatalogItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogItem>
 */
class CatalogItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogItem::class);
    }

    /** @return list<CatalogItem> */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type], ['name' => 'ASC']);
    }

    public function findOneByTypeAndName(string $type, string $name): ?CatalogItem
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->andWhere('LOWER(c.name) = :name')
            ->setParameter('type', $type)
            ->setParameter('name', mb_strtolower($name))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
