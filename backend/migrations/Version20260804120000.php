<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase SUNAT · El hash que devuelve NubeFact supera los 40 caracteres.
 * Se amplía electronic_documents.hash a TEXT para no truncarlo.
 */
final class Version20260804120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Amplía electronic_documents.hash de VARCHAR(40) a TEXT (hash de NubeFact)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE electronic_documents ALTER COLUMN hash TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE electronic_documents ALTER COLUMN hash TYPE VARCHAR(40)');
    }
}
