<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Guías de remisión: documento de venta relacionado (tipo de comprobante y
 * número) que se puede elegir al generar la guía.
 */
final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega comprobante relacionado (tipo y número) a dispatch_guides';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dispatch_guides ADD COLUMN IF NOT EXISTS related_doc_type VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE dispatch_guides ADD COLUMN IF NOT EXISTS related_doc_number VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dispatch_guides DROP COLUMN IF EXISTS related_doc_type');
        $this->addSql('ALTER TABLE dispatch_guides DROP COLUMN IF EXISTS related_doc_number');
    }
}
