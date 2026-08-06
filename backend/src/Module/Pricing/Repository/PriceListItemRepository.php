<?php

declare(strict_types=1);

namespace App\Module\Pricing\Repository;

use App\Module\Pricing\Entity\PriceList;
use App\Module\Pricing\Entity\PriceListItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceListItem>
 */
final class PriceListItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceListItem::class);
    }

    public function findPrice(PriceList $list, string $subjectType, int $subjectId): ?PriceListItem
    {
        return $this->findOneBy([
            'priceList' => $list,
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
        ]);
    }
}
