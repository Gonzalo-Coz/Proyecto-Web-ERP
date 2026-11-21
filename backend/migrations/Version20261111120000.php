<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Índices de rendimiento sobre claves foráneas y columnas muy consultadas que
 * PostgreSQL NO indexa automáticamente (a diferencia de MySQL). Aceleran los
 * joins y los EXISTS de reportes, dashboard y detalles (ventas, taller, compras,
 * comprobantes). Se usan CREATE INDEX IF NOT EXISTS para que sea idempotente y no
 * falle si alguno ya existía.
 */
final class Version20261111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Índices de rendimiento en FKs y columnas de join (sale_items, electronic_documents, service_orders, etc.)';
    }

    public function up(Schema $schema): void
    {
        $indexes = [
            // Líneas de venta: join por venta y filtros por tipo/producto.
            'CREATE INDEX IF NOT EXISTS idx_sale_items_sale ON sale_items (sale_id)',
            'CREATE INDEX IF NOT EXISTS idx_sale_items_type ON sale_items (item_type)',
            'CREATE INDEX IF NOT EXISTS idx_sale_items_part ON sale_items (spare_part_id)',
            'CREATE INDEX IF NOT EXISTS idx_sale_items_unit ON sale_items (motorcycle_unit_id)',
            // Comprobantes por venta (EXISTS de "comprobante aceptado" en reportes).
            'CREATE INDEX IF NOT EXISTS idx_elec_doc_sale ON electronic_documents (sale_id)',
            // Órdenes de taller: enlace con la venta facturada y join de sus ítems.
            'CREATE INDEX IF NOT EXISTS idx_service_orders_invoice_sale ON service_orders (invoice_sale_id)',
            'CREATE INDEX IF NOT EXISTS idx_soi_order ON service_order_items (service_order_id)',
            'CREATE INDEX IF NOT EXISTS idx_soi_part ON service_order_items (spare_part_id)',
            // Líneas de compra: join por compra.
            'CREATE INDEX IF NOT EXISTS idx_purchase_items_purchase ON purchase_items (purchase_id)',
            // Ventas por canal (dashboard: mostrador vs taller).
            'CREATE INDEX IF NOT EXISTS idx_sales_channel ON sales (channel)',
        ];
        foreach ($indexes as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'idx_sale_items_sale', 'idx_sale_items_type', 'idx_sale_items_part', 'idx_sale_items_unit',
            'idx_elec_doc_sale', 'idx_service_orders_invoice_sale', 'idx_soi_order', 'idx_soi_part',
            'idx_purchase_items_purchase', 'idx_sales_channel',
        ] as $name) {
            $this->addSql('DROP INDEX IF EXISTS '.$name);
        }
    }
}
