<?php

declare(strict_types=1);

namespace App\Module\Customer\Service;

use App\Module\Customer\Dto\CustomerTypePayload;
use App\Module\Customer\Entity\CustomerType;
use App\Module\Customer\Repository\CustomerRepository;
use App\Module\Customer\Repository\CustomerTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CustomerTypeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerTypeRepository $repository,
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return array_map($this->toArray(...), $this->repository->findAllOrdered());
    }

    public function create(CustomerTypePayload $payload): array
    {
        $this->assertUniqueName($payload->name, null);

        $type = new CustomerType($payload->name, $payload->discountPercent);
        $type->setActive($payload->isActive);

        $this->entityManager->persist($type);
        $this->entityManager->flush();

        return $this->toArray($type);
    }

    public function update(int $id, CustomerTypePayload $payload): array
    {
        $type = $this->find($id);
        $this->assertUniqueName($payload->name, $id);

        $type->setName($payload->name);
        $type->setDiscountPercent($payload->discountPercent);
        $type->setActive($payload->isActive);

        $this->entityManager->flush();

        return $this->toArray($type);
    }

    /** Eliminación lógica; los clientes que lo usaban quedan sin tipo (SET NULL). */
    public function delete(int $id): void
    {
        $type = $this->find($id);

        // Desvincula a los clientes que lo tenían asignado.
        foreach ($this->customerRepository->findBy(['customerType' => $type]) as $customer) {
            $customer->setCustomerType(null);
        }

        $type->markDeleted();
        $type->setActive(false);
        $this->entityManager->flush();
    }

    private function find(int $id): CustomerType
    {
        return $this->repository->find($id)
            ?? throw new NotFoundHttpException('Tipo de cliente no encontrado.');
    }

    private function assertUniqueName(string $name, ?int $exceptId): void
    {
        $existing = $this->repository->findOneBy(['name' => $name]);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('Ya existe un tipo de cliente llamado "%s".', $name));
        }
    }

    public function toArray(CustomerType $type): array
    {
        return [
            'id' => $type->getId(),
            'name' => $type->getName(),
            'discountPercent' => $type->getDiscountPercent(),
            'isActive' => $type->isActive(),
        ];
    }
}
