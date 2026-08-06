<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Module\Customer\Entity\Customer;
use App\Module\Sales\Entity\Sale;
use PHPUnit\Framework\TestCase;

/**
 * Reglas críticas de CxC (§12 + decisión #4 aprobada).
 */
final class SaleTest extends TestCase
{
    private function makeSale(): Sale
    {
        $customer = new Customer('DNI', '12345678', 'Cliente de Prueba');

        return new Sale($customer, 'tester', new \DateTimeImmutable('2026-01-15'));
    }

    public function testNewSaleStartsPending(): void
    {
        $sale = $this->makeSale();
        $sale->setTotals(100.0, 18.0, 118.0);

        self::assertSame('PENDIENTE', $sale->getPaymentStatus());
        self::assertSame('118.00', $sale->getBalance());
    }

    public function testPartialPaymentSetsParcial(): void
    {
        $sale = $this->makeSale();
        $sale->setTotals(100.0, 18.0, 118.0);
        $sale->registerPaidAmount(50.0);

        self::assertSame('PARCIAL', $sale->getPaymentStatus());
        self::assertSame('68.00', $sale->getBalance());
    }

    public function testFullPaymentSetsPagado(): void
    {
        $sale = $this->makeSale();
        $sale->setTotals(100.0, 18.0, 118.0);
        $sale->registerPaidAmount(50.0);
        $sale->registerPaidAmount(68.0);

        self::assertSame('PAGADO', $sale->getPaymentStatus());
        self::assertSame('0.00', $sale->getBalance());
    }

    public function testOnlyQuotationIsEditable(): void
    {
        $sale = $this->makeSale();
        self::assertTrue($sale->isEditable());

        $sale->markCompleted();
        self::assertFalse($sale->isEditable());
        self::assertSame('COMPLETADA', $sale->getStatus());
        self::assertNotNull($sale->getCompletedAt());
    }
}
