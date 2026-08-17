<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Motos · Moneda de precios de la unidad (PEN/USD): se guarda tal cual, sin convertir.
 */
final class Version20260817120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega motorcycle_units.price_currency (PEN/USD)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE motorcycle_units ADD COLUMN IF NOT EXISTS price_currency VARCHAR(3) DEFAULT 'PEN' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE motorcycle_units DROP COLUMN IF EXISTS price_currency');
    }
}
