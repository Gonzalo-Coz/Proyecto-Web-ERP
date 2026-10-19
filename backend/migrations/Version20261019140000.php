<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Taller: con el flujo nuevo, facturar y entregar es un solo paso. Las órdenes
 * antiguas que se facturaron con el flujo viejo quedaron "facturadas pero en
 * trabajo". Se marcan como ENTREGADA (tienen venta asociada = ya se cerraron).
 */
final class Version20261019140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marca como ENTREGADA las órdenes de taller ya facturadas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE service_orders
             SET status = 'ENTREGADA',
                 delivered_at = COALESCE(delivered_at, updated_at, created_at, NOW())
             WHERE invoice_sale_id IS NOT NULL
               AND status NOT IN ('ENTREGADA', 'ANULADA')",
        );
    }

    public function down(Schema $schema): void
    {
        // No se revierte.
    }
}
