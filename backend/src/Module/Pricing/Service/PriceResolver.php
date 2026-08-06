<?php

declare(strict_types=1);

namespace App\Module\Pricing\Service;

use App\Module\Customer\Repository\CustomerRepository;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Module\Pricing\Repository\PriceListItemRepository;
use App\Module\Pricing\Repository\PriceListRepository;

/**
 * Resolución del precio de venta de un producto para un cliente (Adición A4).
 *
 * Prioridad: precio en la lista asignada al cliente → precio en la lista
 * predeterminada → precio base del producto (repuesto/modelo). Es el ÚNICO
 * punto que decide el precio, de modo que Ventas (y en el futuro Promociones)
 * lo reutilizan sin duplicar reglas.
 */
final class PriceResolver
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly PriceListRepository $listRepository,
        private readonly PriceListItemRepository $itemRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly MotorcycleModelRepository $modelRepository,
    ) {
    }

    /**
     * @return array{price: ?string, source: string} source: 'price_list' | 'base' | 'none'
     */
    public function resolve(?int $customerId, string $subjectType, int $subjectId): array
    {
        $list = null;
        if ($customerId !== null) {
            $customer = $this->customerRepository->find($customerId);
            $list = $customer?->getPriceList();
        }
        if ($list === null || !$list->isActive()) {
            $list = $this->listRepository->findDefault();
        }

        if ($list !== null && $list->isActive()) {
            $item = $this->itemRepository->findPrice($list, $subjectType, $subjectId);
            if ($item !== null) {
                return ['price' => $item->getPrice(), 'source' => 'price_list'];
            }
        }

        $base = $this->basePrice($subjectType, $subjectId);

        return ['price' => $base, 'source' => $base !== null ? 'base' : 'none'];
    }

    private function basePrice(string $subjectType, int $subjectId): ?string
    {
        if ($subjectType === PriceListService::SUBJECT_SPARE_PART) {
            return $this->sparePartRepository->find($subjectId)?->getSalePrice();
        }
        if ($subjectType === PriceListService::SUBJECT_MOTORCYCLE_MODEL) {
            return $this->modelRepository->find($subjectId)?->getReferencePrice();
        }

        return null;
    }
}
