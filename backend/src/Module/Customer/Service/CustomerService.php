<?php

declare(strict_types=1);

namespace App\Module\Customer\Service;

use App\Module\Customer\Dto\CustomerPayload;
use App\Module\Customer\Entity\Customer;
use App\Module\Customer\Repository\CustomerRepository;
use App\Module\Customer\Repository\CustomerTypeRepository;
use App\Module\Pricing\Repository\PriceListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CustomerService
{
    private const SORTABLE = ['name', 'documentNumber', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerRepository $customerRepository,
        private readonly PriceListRepository $priceListRepository,
        private readonly CustomerTypeRepository $customerTypeRepository,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'name';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->customerRepository->createQueryBuilder('c')
            ->orderBy('c.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :s OR c.documentNumber LIKE :s OR LOWER(c.tradeName) LIKE :s OR LOWER(c.email) LIKE :s')
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

    /**
     * Cliente genérico "Público General" para la boleta simple (venta sin pedir
     * DNI/RUC). Se busca por documento reservado 00000000; si no existe, se crea.
     * En el comprobante va como "sin documento" (SUNAT lo permite ≤ S/ 700).
     */
    public function ensureGeneric(): array
    {
        $existing = $this->customerRepository->findOneByDocument('OTRO', Customer::GENERIC_DOC_NUMBER);
        if ($existing !== null) {
            return $this->toArray($existing);
        }

        $customer = new Customer('OTRO', Customer::GENERIC_DOC_NUMBER, 'Público General');
        $customer->setActive(true);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $this->toArray($customer);
    }

    public function create(CustomerPayload $payload): array
    {
        $this->assertDocumentFormat($payload->documentType, $payload->documentNumber);
        $this->assertUnique($payload->documentType, $payload->documentNumber, null);

        $customer = new Customer($payload->documentType, $payload->documentNumber, $payload->name);
        $this->apply($customer, $payload);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $this->toArray($customer);
    }

    public function update(int $id, CustomerPayload $payload): array
    {
        $customer = $this->find($id);
        $this->assertDocumentFormat($payload->documentType, $payload->documentNumber);
        $this->assertUnique($payload->documentType, $payload->documentNumber, $id);

        $customer->setDocumentType($payload->documentType);
        $customer->setDocumentNumber($payload->documentNumber);
        $customer->setName($payload->name);
        $this->apply($customer, $payload);

        $this->entityManager->flush();

        return $this->toArray($customer);
    }

    /** Eliminación lógica: el historial comercial del cliente se conserva (§7). */
    public function delete(int $id): void
    {
        $customer = $this->find($id);
        $customer->markDeleted();
        $customer->setActive(false);
        $this->entityManager->flush();
    }

    private function find(int $id): Customer
    {
        return $this->customerRepository->find($id)
            ?? throw new NotFoundHttpException('Cliente no encontrado.');
    }

    /** Validación de formato por tipo de documento peruano (§7: "Validar formato del documento"). */
    private function assertDocumentFormat(string $type, string $number): void
    {
        $valid = match ($type) {
            'DNI' => preg_match('/^\d{8}$/', $number) === 1,
            'RUC' => preg_match('/^(10|15|17|20)\d{9}$/', $number) === 1,
            'CE' => preg_match('/^[A-Za-z0-9]{6,12}$/', $number) === 1,
            'PASAPORTE' => preg_match('/^[A-Za-z0-9]{6,15}$/', $number) === 1,
            default => preg_match('/^[A-Za-z0-9-]{3,20}$/', $number) === 1,
        };

        if (!$valid) {
            throw new UnprocessableEntityHttpException(
                sprintf('El número "%s" no tiene un formato válido para %s.', $number, $type),
            );
        }
    }

    private function assertUnique(string $type, string $number, ?int $exceptId): void
    {
        $existing = $this->customerRepository->findOneByDocument($type, $number);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(
                sprintf('Ya existe un cliente con %s %s: %s.', $type, $number, $existing->getName()),
            );
        }
    }

    private function apply(Customer $customer, CustomerPayload $payload): void
    {
        $customer->setTradeName($payload->tradeName);
        $customer->setAddress($payload->address);
        $customer->setDistrict($payload->district);
        $customer->setProvince($payload->province);
        $customer->setDepartment($payload->department);
        $customer->setPhone($payload->phone);
        $customer->setMobile($payload->mobile);
        $customer->setEmail($payload->email);
        $customer->setActive($payload->isActive);
        $customer->setCustomerType(
            $payload->customerTypeId !== null ? $this->customerTypeRepository->find($payload->customerTypeId) : null,
        );
        $customer->setPriceList(
            $payload->priceListId !== null ? $this->priceListRepository->find($payload->priceListId) : null,
        );
    }

    public function toArray(Customer $customer): array
    {
        return [
            'id' => $customer->getId(),
            'documentType' => $customer->getDocumentType(),
            'documentNumber' => $customer->getDocumentNumber(),
            'name' => $customer->getName(),
            'tradeName' => $customer->getTradeName(),
            'address' => $customer->getAddress(),
            'district' => $customer->getDistrict(),
            'province' => $customer->getProvince(),
            'department' => $customer->getDepartment(),
            'phone' => $customer->getPhone(),
            'mobile' => $customer->getMobile(),
            'email' => $customer->getEmail(),
            'priceListId' => $customer->getPriceList()?->getId(),
            'priceListName' => $customer->getPriceList()?->getName(),
            'customerTypeId' => $customer->getCustomerType()?->getId(),
            'customerTypeLabel' => $customer->getCustomerTypeLabel(),
            'discountPercent' => $customer->getDiscountPercent(),
            'isLegalEntity' => $customer->isLegalEntity(),
            'isActive' => $customer->isActive(),
            'createdAt' => $customer->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
