<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase SUNAT · Enlaces del proveedor (NubeFact) en el comprobante electrónico.
 * NubeFact hospeda PDF/XML/CDR y devuelve URLs; se guardan solo las rutas.
 */
final class Version20260802120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega pdf_url, xml_url y cdr_url a electronic_documents (enlaces del PSE/OSE)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE electronic_documents ADD pdf_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE electronic_documents ADD xml_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE electronic_documents ADD cdr_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE electronic_documents DROP pdf_url');
        $this->addSql('ALTER TABLE electronic_documents DROP xml_url');
        $this->addSql('ALTER TABLE electronic_documents DROP cdr_url');
    }
}
