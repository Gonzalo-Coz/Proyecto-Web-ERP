<?php

declare(strict_types=1);

namespace App\Module\Supplier\Service;

use App\Module\Supplier\Dto\SupplierPayload;
use App\Module\Supplier\Entity\Supplier;
use App\Module\Supplier\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SupplierService
{
    private const SORTABLE = ['businessName', 'ruc', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SupplierRepository $supplierRepository,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'businessName';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->supplierRepository->createQueryBuilder('s')
            ->orderBy('s.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(s.businessName) LIKE :s OR s.ruc LIKE :s OR LOWER(s.tradeName) LIKE :s OR LOWER(s.contactPerson) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->toArray(...), iterator_to_array($paginator, false)),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id));
    }

    public function create(SupplierPayload $payload): array
    {
        $this->assertUnique($payload->ruc, null);

        $supplier = new Supplier($payload->ruc, $payload->businessName);
        $this->apply($supplier, $payload);

        $this->entityManager->persist($supplier);
        $this->entityManager->flush();

        return $this->toArray($supplier);
    }

    public function update(int $id, SupplierPayload $payload): array
    {
        $supplier = $this->find($id);
        $this->assertUnique($payload->ruc, $id);

        $supplier->setRuc($payload->ruc);
        $supplier->setBusinessName($payload->businessName);
        $this->apply($supplier, $payload);

        $this->entityManager->flush();

        return $this->toArray($supplier);
    }

    public function delete(int $id): void
    {
        $supplier = $this->find($id);
        $supplier->markDeleted();
        $supplier->setActive(false);
        $this->entityManager->flush();
    }

    private function find(int $id): Supplier
    {
        return $this->supplierRepository->find($id)
            ?? throw new NotFoundHttpException('Proveedor no encontrado.');
    }

    private function assertUnique(string $ruc, ?int $exceptId): void
    {
        $existing = $this->supplierRepository->findOneByRuc($ruc);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(
                sprintf('Ya existe un proveedor con RUC %s: %s.', $ruc, $existing->getBusinessName()),
            );
        }
    }

    private function apply(Supplier $supplier, SupplierPayload $payload): void
    {
        $supplier->setTradeName($payload->tradeName);
        $supplier->setAddress($payload->address);
        $supplier->setCity($payload->city);
        $supplier->setPhone($payload->phone);
        $supplier->setEmail($payload->email);
        $supplier->setContactPerson($payload->contactPerson);
        $supplier->setActive($payload->isActive);
    }

    public function toArray(Supplier $supplier): array
    {
        return [
            'id' => $supplier->getId(),
            'ruc' => $supplier->getRuc(),
            'businessName' => $supplier->getBusinessName(),
            'tradeName' => $supplier->getTradeName(),
            'address' => $supplier->getAddress(),
            'city' => $supplier->getCity(),
            'phone' => $supplier->getPhone(),
            'email' => $supplier->getEmail(),
            'contactPerson' => $supplier->getContactPerson(),
            'isActive' => $supplier->isActive(),
            'createdAt' => $supplier->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
