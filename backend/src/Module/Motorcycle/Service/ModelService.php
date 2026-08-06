<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Service;

use App\Module\Catalog\Repository\CatalogItemRepository;
use App\Module\Motorcycle\Dto\ModelPayload;
use App\Module\Motorcycle\Entity\MotorcycleModel;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Shared\Pricing\Service\PriceHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ModelService
{
    private const SORTABLE = ['model', 'modelYear', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MotorcycleModelRepository $modelRepository,
        private readonly CatalogItemRepository $catalogRepository,
        private readonly PriceHistoryService $priceHistory,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'model';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->modelRepository->createQueryBuilder('m')
            ->join('m.brand', 'b')->addSelect('b')
            ->orderBy('m.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(m.model) LIKE :s OR LOWER(m.version) LIKE :s OR LOWER(b.name) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->toArray(...), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id));
    }

    public function create(ModelPayload $payload): array
    {
        $model = new MotorcycleModel($this->findBrand($payload->brandId), $payload->model, $payload->modelYear);
        $this->apply($model, $payload);

        $this->entityManager->persist($model);
        $this->entityManager->flush();

        // Historial de precios (A3): precio de referencia inicial.
        $this->priceHistory->record(
            PriceHistoryService::SUBJECT_MOTORCYCLE_MODEL,
            (int) $model->getId(),
            $this->priceLabel($model),
            null,
            $model->getReferencePrice(),
            $payload->priceChangeReason,
        );

        return $this->toArray($model);
    }

    /** Etiqueta legible del modelo para el historial de precios. */
    private function priceLabel(MotorcycleModel $model): string
    {
        return mb_substr($model->getFullName(), 0, 200);
    }

    public function update(int $id, ModelPayload $payload): array
    {
        $model = $this->find($id);
        $oldReferencePrice = $model->getReferencePrice();

        $model->setBrand($this->findBrand($payload->brandId));
        $model->setModel($payload->model);
        $model->setModelYear($payload->modelYear);
        $this->apply($model, $payload);

        $this->entityManager->flush();

        // Historial de precios (A3): cambio del precio de referencia con su motivo.
        $this->priceHistory->record(
            PriceHistoryService::SUBJECT_MOTORCYCLE_MODEL,
            $id,
            $this->priceLabel($model),
            $oldReferencePrice,
            $model->getReferencePrice(),
            $payload->priceChangeReason,
        );

        return $this->toArray($model);
    }

    public function delete(int $id): void
    {
        $model = $this->find($id);
        $model->markDeleted();
        $model->setActive(false);
        $this->entityManager->flush();
    }

    private function find(int $id): MotorcycleModel
    {
        return $this->modelRepository->find($id)
            ?? throw new NotFoundHttpException('Modelo no encontrado.');
    }

    private function findBrand(int $brandId): \App\Module\Catalog\Entity\CatalogItem
    {
        $brand = $this->catalogRepository->find($brandId);
        if ($brand === null || $brand->getType() !== 'brands' || !$brand->isActive()) {
            throw new UnprocessableEntityHttpException('Marca inválida o inactiva.');
        }

        return $brand;
    }

    private function apply(MotorcycleModel $model, ModelPayload $payload): void
    {
        $model->setVersion($payload->version);
        $model->setEngineCapacity($payload->engineCapacity);
        $model->setEngineType($payload->engineType);
        $model->setPower($payload->power);
        $model->setFuelType($payload->fuelType);
        $model->setTransmission($payload->transmission);
        $model->setTankCapacity($payload->tankCapacity);
        $model->setWeight($payload->weight);
        $model->setColors($payload->colors);
        $model->setWarrantyMonths($payload->warrantyMonths);
        $model->setReferencePrice($payload->referencePrice !== null ? number_format($payload->referencePrice, 2, '.', '') : null);
        $model->setActive($payload->isActive);
    }

    public function toArray(MotorcycleModel $model): array
    {
        return [
            'id' => $model->getId(),
            'brandId' => $model->getBrand()->getId(),
            'brandName' => $model->getBrand()->getName(),
            'model' => $model->getModel(),
            'version' => $model->getVersion(),
            'modelYear' => $model->getModelYear(),
            'engineCapacity' => $model->getEngineCapacity(),
            'engineType' => $model->getEngineType(),
            'power' => $model->getPower(),
            'fuelType' => $model->getFuelType(),
            'transmission' => $model->getTransmission(),
            'tankCapacity' => $model->getTankCapacity(),
            'weight' => $model->getWeight(),
            'colors' => $model->getColors(),
            'warrantyMonths' => $model->getWarrantyMonths(),
            'referencePrice' => $model->getReferencePrice(),
            'fullName' => $model->getFullName(),
            'isActive' => $model->isActive(),
        ];
    }
}
