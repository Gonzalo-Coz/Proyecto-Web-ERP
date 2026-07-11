<?php

declare(strict_types=1);

namespace App\Module\Customer\Repository;

use App\Module\Customer\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function findOneByDocument(string $documentType, string $documentNumber): ?Customer
    {
        return $this->findOneBy(['documentType' => $documentType, 'documentNumber' => $documentNumber]);
    }
}
