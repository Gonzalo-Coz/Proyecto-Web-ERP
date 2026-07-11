<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración manual: convierte los índices únicos de users y roles en
 * índices parciales (WHERE deleted_at IS NULL).
 *
 * Motivo: la unicidad debe aplicar solo a registros vigentes, permitiendo
 * reutilizar username/email/código de registros eliminados lógicamente
 * (§23.7 del Documento Maestro). Se escribe a mano porque el comparador
 * de Doctrine DBAL no detecta cambios en la cláusula WHERE de un índice.
 */
final class Version20260711001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unicidad parcial (solo registros no eliminados) en users.username, users.email y roles.code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uq_user_username');
        $this->addSql('DROP INDEX uq_user_email');
        $this->addSql('DROP INDEX uq_role_code');

        $this->addSql('CREATE UNIQUE INDEX uq_user_username ON users (username) WHERE deleted_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_user_email ON users (email) WHERE deleted_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_role_code ON roles (code) WHERE deleted_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Nota: fallará si existen duplicados entre registros eliminados y vigentes.
        $this->addSql('DROP INDEX uq_user_username');
        $this->addSql('DROP INDEX uq_user_email');
        $this->addSql('DROP INDEX uq_role_code');

        $this->addSql('CREATE UNIQUE INDEX uq_user_username ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX uq_user_email ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX uq_role_code ON roles (code)');
    }
}
