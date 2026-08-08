<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seguridad · El correo del usuario pasa a ser opcional (nullable).
 */
final class Version20260807190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hace opcional (nullable) el correo del usuario';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE users SET email = username || '@sin-correo.local' WHERE email IS NULL");
        $this->addSql('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
    }
}
