<?php

declare(strict_types=1);

namespace App\Module\Security\Repository;

use App\Module\Security\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findOneByCode(string $code): ?Role
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }
}
