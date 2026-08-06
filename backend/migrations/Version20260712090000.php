<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Perfil de usuario: teléfono y ruta de la fotografía de perfil.
 * Aditiva y sin datos previos afectados (columnas nullable).
 */
final class Version20260712090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade users.phone y users.avatar_path para la edición de perfil';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD phone VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD avatar_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP phone');
        $this->addSql('ALTER TABLE users DROP avatar_path');
    }
}
