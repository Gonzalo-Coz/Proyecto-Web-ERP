<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ventas · Operación exonerada de IGV (Amazonía, Ley 27037).
 */
final class Version20260813120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega sales.igv_exempt (operación exonerada de IGV - Amazonía)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales ADD COLUMN IF NOT EXISTS igv_exempt BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP COLUMN IF EXISTS igv_exempt');
    }
}
