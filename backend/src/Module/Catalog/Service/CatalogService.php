<?php

declare(strict_types=1);

namespace App\Module\Catalog\Service;

use App\Module\Catalog\Dto\CatalogItemPayload;
use App\Module\Catalog\Entity\CatalogItem;
use App\Module\Catalog\Repository\CatalogItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CatalogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CatalogItemRepository $repository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(string $type): array
    {
        return array_map($this->toArray(...), $this->repository->findByType($type));
    }

    public function create(string $type, CatalogItemPayload $payload): array
    {
        $this->assertUnique($type, $payload->name, null);

        $item = new CatalogItem($type, $payload->name);
        $item->setCode($payload->code ?: null);
        $item->setActive($payload->isActive);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->toArray($item);
    }

    public function update(string $type, int $id, CatalogItemPayload $payload): array
    {
        $item = $this->find($type, $id);
        $this->assertUnique($type, $payload->name, $id);

        $item->setName($payload->name);
        $item->setCode($payload->code ?: null);
        $item->setActive($payload->isActive);

        $this->entityManager->flush();

        return $this->toArray($item);
    }

    public function delete(string $type, int $id): void
    {
        $item = $this->find($type, $id);
        $item->markDeleted();
        $item->setActive(false);
        $this->entityManager->flush();
    }

    private function find(string $type, int $id): CatalogItem
    {
        $item = $this->repository->find($id);
        if ($item === null || $item->getType() !== $type) {
            throw new NotFoundHttpException('Elemento de catálogo no encontrado.');
        }

        return $item;
    }

    private function assertUnique(string $type, string $name, ?int $exceptId): void
    {
        $existing = $this->repository->findOneByTypeAndName($type, $name);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('Ya existe "%s" en este catálogo.', $existing->getName()));
        }
    }

    public function toArray(CatalogItem $item): array
    {
        return [
            'id' => $item->getId(),
            'type' => $item->getType(),
            'name' => $item->getName(),
            'code' => $item->getCode(),
            'isActive' => $item->isActive(),
        ];
    }
}
