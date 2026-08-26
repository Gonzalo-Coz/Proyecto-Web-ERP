<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Taller: origen del ítem (plan de mantenimiento vs adicional) y "traído por"
 * (quién ingresa la moto / a nombre de otra persona).
 */
final class Version20260826120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega service_order_items.from_plan y service_orders.brought_by';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE service_order_items ADD COLUMN IF NOT EXISTS from_plan BOOLEAN DEFAULT false NOT NULL");
        $this->addSql("ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS brought_by VARCHAR(150) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_order_items DROP COLUMN IF EXISTS from_plan');
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS brought_by');
    }
}
