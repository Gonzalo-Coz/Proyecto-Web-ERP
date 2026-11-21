<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Órdenes de taller por GARANTÍA: agrega la bandera is_warranty a service_orders.
 * Una orden de garantía registra el mantenimiento (repuestos y mano de obra) y
 * descuenta el stock consumido, pero NO genera venta ni cobro.
 */
final class Version20261121120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega is_warranty a service_orders (órdenes de taller por garantía, sin cobro)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS is_warranty BOOLEAN DEFAULT false NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS is_warranty');
    }
}
