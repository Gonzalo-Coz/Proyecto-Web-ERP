<?php

declare(strict_types=1);

namespace App\Module\Pricing\Service;

use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Module\Pricing\Dto\PriceListPayload;
use App\Module\Pricing\Entity\PriceList;
use App\Module\Pricing\Entity\PriceListItem;
use App\Module\Pricing\Repository\PriceListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Gestión de listas de precios (Adición A4 · §24.5). Controller fino → aquí
 * vive la lógica. Reutiliza los repos de productos para validar y etiquetar
 * cada línea, sin duplicar reglas.
 */
final class PriceListService
{
    public const SUBJECT_SPARE_PART = 'spare_part';
    public const SUBJECT_MOTORCYCLE_MODEL = 'motorcycle_model';

    private const SORTABLE = ['code', 'name', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PriceListRepository $listRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly MotorcycleModelRepository $modelRepository,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'name';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->listRepository->createQueryBuilder('l')
            ->orderBy('l.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(l.code) LIKE :s OR LOWER(l.name) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->toRow(...), iterator_to_array($paginator, false)),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->toDetail($this->find($id));
    }

    /** @return array<string, mixed> */
    public function create(PriceListPayload $payload): array
    {
        $this->assertUniqueCode($payload->code, null);

        $list = new PriceList($payload->code, $payload->name);
        $list->setActive($payload->isActive);
        $this->applyDefault($list, $payload->isDefault);
        $this->syncItems($list, $payload->items);

        $this->entityManager->persist($list);
        $this->entityManager->flush();

        return $this->toDetail($list);
    }

    /** @return array<string, mixed> */
    public function update(int $id, PriceListPayload $payload): array
    {
        $list = $this->find($id);
        $this->assertUniqueCode($payload->code, $id);

        $list->setCode($payload->code);
        $list->setName($payload->name);
        $list->setActive($payload->isActive);
        $this->applyDefault($list, $payload->isDefault);
        $this->syncItems($list, $payload->items);

        $this->entityManager->flush();

        return $this->toDetail($list);
    }

    /** Eliminación lógica (§23.7). */
    public function delete(int $id): void
    {
        $list = $this->find($id);
        $list->markDeleted();
        $list->setActive(false);
        $list->setDefault(false);
        $this->entityManager->flush();
    }

    private function find(int $id): PriceList
    {
        return $this->listRepository->find($id)
            ?? throw new NotFoundHttpException('Lista de precios no encontrada.');
    }

    private function assertUniqueCode(string $code, ?int $exceptId): void
    {
        $existing = $this->listRepository->findOneByCode($code);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El código "%s" ya está en uso.', $code));
        }
    }

    /** Garantiza una única lista predeterminada activa. */
    private function applyDefault(PriceList $list, bool $isDefault): void
    {
        $list->setDefault($isDefault);
        if (!$isDefault) {
            return;
        }
        foreach ($this->listRepository->findBy(['isDefault' => true]) as $other) {
            if ($other !== $list) {
                $other->setDefault(false);
            }
        }
    }

    /** @param list<array{subjectType?: string, subjectId?: int, price?: float}> $items */
    private function syncItems(PriceList $list, array $items): void
    {
        $list->clearItems();
        $seen = [];
        foreach ($items as $pos => $line) {
            $subjectType = (string) ($line['subjectType'] ?? '');
            $subjectId = (int) ($line['subjectId'] ?? 0);
            $price = round((float) ($line['price'] ?? 0), 2);

            $label = $this->resolveLabel($subjectType, $subjectId, $pos + 1);
            $key = $subjectType.'#'.$subjectId;
            if (isset($seen[$key])) {
                throw new UnprocessableEntityHttpException(sprintf('El producto "%s" está repetido en la lista.', $label));
            }
            if ($price < 0) {
                throw new UnprocessableEntityHttpException(sprintf('Precio inválido para "%s".', $label));
            }
            $seen[$key] = true;

            $list->addItem(new PriceListItem($list, $subjectType, $subjectId, $label, number_format($price, 2, '.', '')));
        }
    }

    private function resolveLabel(string $subjectType, int $subjectId, int $position): string
    {
        if ($subjectType === self::SUBJECT_SPARE_PART) {
            $part = $this->sparePartRepository->find($subjectId)
                ?? throw new UnprocessableEntityHttpException(sprintf('Línea %d: repuesto no encontrado.', $position));

            return mb_substr(sprintf('%s · %s', $part->getInternalCode(), $part->getDescription()), 0, 200);
        }
        if ($subjectType === self::SUBJECT_MOTORCYCLE_MODEL) {
            $model = $this->modelRepository->find($subjectId)
                ?? throw new UnprocessableEntityHttpException(sprintf('Línea %d: modelo no encontrado.', $position));

            return mb_substr($model->getFullName(), 0, 200);
        }

        throw new UnprocessableEntityHttpException(sprintf('Línea %d: tipo de producto inválido.', $position));
    }

    /** @return array<string, mixed> */
    private function toRow(PriceList $list): array
    {
        return [
            'id' => $list->getId(),
            'code' => $list->getCode(),
            'name' => $list->getName(),
            'isDefault' => $list->isDefault(),
            'isActive' => $list->isActive(),
            'itemCount' => $list->getItems()->count(),
            'createdAt' => $list->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function toDetail(PriceList $list): array
    {
        $row = $this->toRow($list);
        $row['items'] = array_map(static fn (PriceListItem $i) => [
            'id' => $i->getId(),
            'subjectType' => $i->getSubjectType(),
            'subjectId' => $i->getSubjectId(),
            'subjectLabel' => $i->getSubjectLabel(),
            'price' => $i->getPrice(),
        ], $list->getItems()->toArray());

        return $row;
    }
}
