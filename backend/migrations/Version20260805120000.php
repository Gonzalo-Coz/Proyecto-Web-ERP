<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ventas · Modo de IGV por venta: incluido (zona local/Tingo María) o agregado
 * (venta al exterior/fuera de zona).
 */
final class Version20260805120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega igv_included a sales (IGV incluido vs. agregado)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales ADD igv_included BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP igv_included');
    }
}
