<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Module\Inventory\Entity\SparePart;
use PHPUnit\Framework\TestCase;

/**
 * Regla crítica §10/§19: el stock nunca puede quedar negativo.
 */
final class SparePartStockTest extends TestCase
{
    public function testStockIncreasesAndDecreases(): void
    {
        $part = new SparePart('REP-001', '5SL-E3440-00', 'Filtro de aceite');
        $part->applyStockChange(10);
        self::assertSame(10, $part->getStock());

        $part->applyStockChange(-4);
        self::assertSame(6, $part->getStock());
    }

    public function testNegativeStockIsRejected(): void
    {
        $part = new SparePart('REP-001', '5SL-E3440-00', 'Filtro de aceite');
        $part->applyStockChange(5);

        $this->expectException(\DomainException::class);
        $part->applyStockChange(-6);
    }

    public function testLowAndOutOfStockFlags(): void
    {
        $part = new SparePart('REP-001', '5SL-E3440-00', 'Filtro de aceite');
        $part->setMinStock(3);

        self::assertTrue($part->isOutOfStock());

        $part->applyStockChange(2);
        self::assertTrue($part->isLowStock());
        self::assertFalse($part->isOutOfStock());

        $part->applyStockChange(10);
        self::assertFalse($part->isLowStock());
    }
}
