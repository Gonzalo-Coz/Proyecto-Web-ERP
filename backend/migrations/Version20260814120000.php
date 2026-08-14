<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ventas · Moneda de la venta (PEN/USD): se emite tal cual, sin convertir.
 */
final class Version20260814120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega sales.currency (PEN/USD)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sales ADD COLUMN IF NOT EXISTS currency VARCHAR(3) DEFAULT 'PEN' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS currency');
    }
}
