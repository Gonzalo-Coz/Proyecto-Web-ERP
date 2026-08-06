<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Motos · Datos de importación (DUA) para el comprobante de venta de vehículos.
 */
final class Version20260804130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega dua_number y dua_item a motorcycle_units (DUA para comprobante de vehículos)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE motorcycle_units ADD dua_number VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE motorcycle_units ADD dua_item VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE motorcycle_units DROP dua_number');
        $this->addSql('ALTER TABLE motorcycle_units DROP dua_item');
    }
}
