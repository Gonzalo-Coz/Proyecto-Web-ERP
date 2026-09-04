<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ventas: datos del reporte Yamaha de venta de motos (tipo de pago, entidad
 * financiera, TCEA, bonos y campaña) que se llenan al vender una moto.
 */
final class Version20260826200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega datos retail (reporte Yamaha) a sales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales ADD COLUMN IF NOT EXISTS retail_payment_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD COLUMN IF NOT EXISTS retail_financial_entity VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD COLUMN IF NOT EXISTS retail_tcea NUMERIC(6, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD COLUMN IF NOT EXISTS retail_bonus_ymdp NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD COLUMN IF NOT EXISTS retail_bonus_dealer NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD COLUMN IF NOT EXISTS retail_campaign VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS retail_payment_type');
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS retail_financial_entity');
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS retail_tcea');
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS retail_bonus_ymdp');
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS retail_bonus_dealer');
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS retail_campaign');
    }
}
