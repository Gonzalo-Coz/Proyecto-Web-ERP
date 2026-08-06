<?php

declare(strict_types=1);

namespace App\Module\Promotion\Service;

use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Module\Promotion\Entity\Promotion;
use App\Module\Promotion\Repository\PromotionRepository;

/**
 * Resolución de promociones aplicables a un producto (Adición A5).
 *
 * Es el punto único que decide qué descuento automático y qué bonificaciones
 * corresponden. Ventas lo consulta para prellenar el % de descuento por línea
 * (reutilizando A1) y ofrecer las bonificaciones como líneas a S/ 0.00, sin
 * alterar la regla de negocio (el backend sigue tomando los valores de la línea).
 */
final class PromotionResolver
{
    public const SUBJECT_SPARE_PART = 'spare_part';
    public const SUBJECT_MOTORCYCLE_MODEL = 'motorcycle_model';

    public function __construct(
        private readonly PromotionRepository $promotionRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly MotorcycleModelRepository $modelRepository,
    ) {
    }

    /**
     * @return array{discountPercent: float, bonuses: list<array<string, mixed>>}
     */
    public function applicable(string $subjectType, int $subjectId, \DateTimeImmutable $date): array
    {
        $attrs = $this->productScope($subjectType, $subjectId);
        if ($attrs === null) {
            return ['discountPercent' => 0.0, 'bonuses' => []];
        }

        $discount = 0.0;
        $bonuses = [];
        foreach ($this->promotionRepository->findActiveOn($date) as $promo) {
            if (!$this->matches($promo, $attrs)) {
                continue;
            }
            if ($promo->getType() === 'DISCOUNT') {
                $discount = max($discount, (float) $promo->getDiscountPercent());
            } elseif ($promo->getType() === 'BONUS' && $promo->getBonusSubjectId() !== null) {
                $bonuses[] = [
                    'promotion' => $promo->getName(),
                    'subjectType' => $promo->getBonusSubjectType(),
                    'subjectId' => $promo->getBonusSubjectId(),
                    'label' => $promo->getBonusLabel(),
                    'quantity' => $promo->getBonusQuantity() ?? 1,
                ];
            }
        }

        return ['discountPercent' => round($discount, 2), 'bonuses' => $bonuses];
    }

    /**
     * @return array{brandId: ?int, categoryId: ?int, modelIds: list<int>}|null
     */
    private function productScope(string $subjectType, int $subjectId): ?array
    {
        if ($subjectType === self::SUBJECT_SPARE_PART) {
            $part = $this->sparePartRepository->find($subjectId);
            if ($part === null) {
                return null;
            }

            return [
                'brandId' => $part->getBrand()?->getId(),
                'categoryId' => $part->getCategory()?->getId(),
                'modelIds' => array_values(array_map(static fn ($m) => $m->getId(), $part->getCompatibleModels()->toArray())),
            ];
        }
        if ($subjectType === self::SUBJECT_MOTORCYCLE_MODEL) {
            $model = $this->modelRepository->find($subjectId);
            if ($model === null) {
                return null;
            }

            return [
                'brandId' => $model->getBrand()->getId(),
                'categoryId' => null,
                'modelIds' => [(int) $model->getId()],
            ];
        }

        return null;
    }

    /** @param array{brandId: ?int, categoryId: ?int, modelIds: list<int>} $attrs */
    private function matches(Promotion $promo, array $attrs): bool
    {
        return match ($promo->getScopeType()) {
            'ALL' => true,
            'BRAND' => $attrs['brandId'] !== null && $promo->getScopeRefId() === $attrs['brandId'],
            'CATEGORY' => $attrs['categoryId'] !== null && $promo->getScopeRefId() === $attrs['categoryId'],
            'MODEL' => $promo->getScopeRefId() !== null && in_array($promo->getScopeRefId(), $attrs['modelIds'], true),
            default => false,
        };
    }
}
