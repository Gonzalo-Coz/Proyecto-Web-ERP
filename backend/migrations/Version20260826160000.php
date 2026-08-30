<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Taller: datos capturados en la recepción para la Orden de Servicio
 * (marca, color, N° de serie de la moto y teléfono/email de contacto).
 */
final class Version20260826160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega datos de recepción (moto y contacto) a service_orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS moto_brand VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS moto_color VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS moto_serial VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS contact_email VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS moto_brand');
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS moto_color');
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS moto_serial');
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS contact_phone');
        $this->addSql('ALTER TABLE service_orders DROP COLUMN IF EXISTS contact_email');
    }
}
