<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Taller: hora de entrada de la moto y tiempo estimado de trabajo en horas.
 */
final class Version20260826140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega service_orders.entry_time y service_orders.estimated_hours';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS entry_time VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS estimated_hours NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS entry_time');
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS estimated_hours');
    }
}
