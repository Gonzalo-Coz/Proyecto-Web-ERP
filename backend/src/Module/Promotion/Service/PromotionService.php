<?php

declare(strict_types=1);

namespace App\Module\Promotion\Service;

use App\Module\Catalog\Repository\CatalogItemRepository;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Module\Promotion\Dto\PromotionPayload;
use App\Module\Promotion\Entity\Promotion;
use App\Module\Promotion\Repository\PromotionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Gestión de promociones (Adición A5 · §24.3). Controller fino → aquí la lógica.
 */
final class PromotionService
{
    private const SORTABLE = ['code', 'name', 'startDate', 'endDate', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PromotionRepository $promotionRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly MotorcycleModelRepository $modelRepository,
        private readonly CatalogItemRepository $catalogRepository,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'startDate';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        $qb = $this->promotionRepository->createQueryBuilder('p')
            ->orderBy('p.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(p.code) LIKE :s OR LOWER(p.name) LIKE :s')
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

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->toArray($this->find($id));
    }

    /** @return array<string, mixed> */
    public function create(PromotionPayload $payload): array
    {
        $this->assertUniqueCode($payload->code, null);
        [$start, $end] = $this->validateDates($payload);

        $promo = new Promotion($payload->code, $payload->name, $payload->type, $start, $end);
        $this->apply($promo, $payload);

        $this->entityManager->persist($promo);
        $this->entityManager->flush();

        return $this->toArray($promo);
    }

    /** @return array<string, mixed> */
    public function update(int $id, PromotionPayload $payload): array
    {
        $promo = $this->find($id);
        $this->assertUniqueCode($payload->code, $id);
        [$start, $end] = $this->validateDates($payload);

        $promo->setCode($payload->code);
        $promo->setName($payload->name);
        $promo->setType($payload->type);
        $promo->setStartDate($start);
        $promo->setEndDate($end);
        $this->apply($promo, $payload);

        $this->entityManager->flush();

        return $this->toArray($promo);
    }

    public function delete(int $id): void
    {
        $promo = $this->find($id);
        $promo->markDeleted();
        $promo->setActive(false);
        $this->entityManager->flush();
    }

    private function find(int $id): Promotion
    {
        return $this->promotionRepository->find($id)
            ?? throw new NotFoundHttpException('Promoción no encontrada.');
    }

    private function assertUniqueCode(string $code, ?int $exceptId): void
    {
        $existing = $this->promotionRepository->findOneByCode($code);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El código "%s" ya está en uso.', $code));
        }
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function validateDates(PromotionPayload $payload): array
    {
        $start = new \DateTimeImmutable($payload->startDate);
        $end = new \DateTimeImmutable($payload->endDate);
        if ($end < $start) {
            throw new UnprocessableEntityHttpException('La fecha de fin no puede ser anterior a la de inicio.');
        }

        return [$start, $end];
    }

    private function apply(Promotion $promo, PromotionPayload $payload): void
    {
        $promo->setActive($payload->isActive);
        $promo->setScopeType($payload->scopeType);
        $promo->setScopeRefId($payload->scopeType === 'ALL' ? null : $payload->scopeRefId);

        if ($payload->scopeType !== 'ALL' && $payload->scopeRefId === null) {
            throw new UnprocessableEntityHttpException('Debes indicar el elemento del alcance (marca, categoría o modelo).');
        }

        if ($payload->type === 'DISCOUNT') {
            if ($payload->discountPercent === null || $payload->discountPercent <= 0 || $payload->discountPercent > 100) {
                throw new UnprocessableEntityHttpException('El descuento debe estar entre 0 y 100 %.');
            }
            $promo->setDiscountPercent(number_format($payload->discountPercent, 2, '.', ''));
            $promo->setBonusSubjectType(null);
            $promo->setBonusSubjectId(null);
            $promo->setBonusLabel(null);
            $promo->setBonusQuantity(null);
        } else { // BONUS
            $label = $this->resolveBonusLabel($payload->bonusSubjectType, $payload->bonusSubjectId);
            $promo->setDiscountPercent(null);
            $promo->setBonusSubjectType($payload->bonusSubjectType);
            $promo->setBonusSubjectId($payload->bonusSubjectId);
            $promo->setBonusLabel($label);
            $promo->setBonusQuantity(max(1, $payload->bonusQuantity ?? 1));
        }
    }

    private function resolveBonusLabel(?string $subjectType, ?int $subjectId): string
    {
        if ($subjectId === null) {
            throw new UnprocessableEntityHttpException('Selecciona el producto de la bonificación.');
        }
        if ($subjectType === PromotionResolver::SUBJECT_SPARE_PART) {
            $part = $this->sparePartRepository->find($subjectId)
                ?? throw new UnprocessableEntityHttpException('Repuesto de bonificación no encontrado.');

            return mb_substr(sprintf('%s · %s', $part->getInternalCode(), $part->getDescription()), 0, 200);
        }
        if ($subjectType === PromotionResolver::SUBJECT_MOTORCYCLE_MODEL) {
            $model = $this->modelRepository->find($subjectId)
                ?? throw new UnprocessableEntityHttpException('Modelo de bonificación no encontrado.');

            return mb_substr($model->getFullName(), 0, 200);
        }

        throw new UnprocessableEntityHttpException('Tipo de producto de bonificación inválido.');
    }

    /** Etiqueta legible del alcance para el listado. */
    private function scopeLabel(Promotion $promo): string
    {
        return match ($promo->getScopeType()) {
            'ALL' => 'Todos los productos',
            'BRAND' => 'Marca: '.($this->catalogRepository->find($promo->getScopeRefId())?->getName() ?? '—'),
            'CATEGORY' => 'Categoría: '.($this->catalogRepository->find($promo->getScopeRefId())?->getName() ?? '—'),
            'MODEL' => 'Modelo: '.($this->modelRepository->find($promo->getScopeRefId())?->getFullName() ?? '—'),
            default => '—',
        };
    }

    /** @return array<string, mixed> */
    public function toArray(Promotion $promo): array
    {
        return [
            'id' => $promo->getId(),
            'code' => $promo->getCode(),
            'name' => $promo->getName(),
            'type' => $promo->getType(),
            'discountPercent' => $promo->getDiscountPercent(),
            'scopeType' => $promo->getScopeType(),
            'scopeRefId' => $promo->getScopeRefId(),
            'scopeLabel' => $this->scopeLabel($promo),
            'bonusSubjectType' => $promo->getBonusSubjectType(),
            'bonusSubjectId' => $promo->getBonusSubjectId(),
            'bonusLabel' => $promo->getBonusLabel(),
            'bonusQuantity' => $promo->getBonusQuantity(),
            'startDate' => $promo->getStartDate()->format('Y-m-d'),
            'endDate' => $promo->getEndDate()->format('Y-m-d'),
            'isActive' => $promo->isActive(),
        ];
    }
}
