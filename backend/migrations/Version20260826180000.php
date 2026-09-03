<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ventas: canal/categoría MOSTRADOR vs TALLER (las generadas desde una orden
 * de servicio se marcan como TALLER).
 */
final class Version20260826180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega sales.channel (MOSTRADOR/TALLER)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sales ADD COLUMN IF NOT EXISTS channel VARCHAR(12) DEFAULT 'MOSTRADOR' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS channel');
    }
}
