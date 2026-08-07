<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clientes · Tipo de cliente (lista fija) con % de descuento por defecto,
 * aplicado automáticamente en la venta.
 */
final class Version20260806140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega customer_type a customers (tipo de cliente con descuento)';
    }

    public function up(Schema $schema): void
    {
        // IF NOT EXISTS: la columna pudo crearse manualmente en producción;
        // así el deploy no falla si ya existe.
        $this->addSql("ALTER TABLE customers ADD COLUMN IF NOT EXISTS customer_type VARCHAR(20) DEFAULT 'GENERAL' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS customer_type');
    }
}
