<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Comprobantes: referencia al documento que modifica una nota de crédito/débito
 * (tipo, serie, número y fecha), requerida en el Registro de Ventas de SUNAT.
 */
final class Version20260930150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega documento modificado (tipo/serie/número/fecha) a electronic_documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE electronic_documents ADD COLUMN IF NOT EXISTS modifies_doc_type VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE electronic_documents ADD COLUMN IF NOT EXISTS modifies_series VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE electronic_documents ADD COLUMN IF NOT EXISTS modifies_correlative INT DEFAULT NULL');
        $this->addSql('ALTER TABLE electronic_documents ADD COLUMN IF NOT EXISTS modifies_issue_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE electronic_documents DROP COLUMN IF EXISTS modifies_doc_type');
        $this->addSql('ALTER TABLE electronic_documents DROP COLUMN IF EXISTS modifies_series');
        $this->addSql('ALTER TABLE electronic_documents DROP COLUMN IF EXISTS modifies_correlative');
        $this->addSql('ALTER TABLE electronic_documents DROP COLUMN IF EXISTS modifies_issue_date');
    }
}
