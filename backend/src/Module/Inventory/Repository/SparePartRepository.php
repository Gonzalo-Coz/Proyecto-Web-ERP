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

    /** Siguiente código interno correlativo (R-0001, R-0002, …). */
    public function nextInternalCode(): string
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.internalCode')
            ->where("p.internalCode LIKE 'R-%'")
            ->getQuery()
            ->getScalarResult();

        $max = 0;
        foreach ($rows as $r) {
            if (preg_match('/^R-(\d+)$/', (string) $r['internalCode'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'R-'.str_pad((string) ($max + 1), 4, '0', \STR_PAD_LEFT);
    }
}
