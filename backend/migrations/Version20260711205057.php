<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260711205057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE electronic_documents ADD discount_total NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE roles ADD max_discount_percent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE sale_items ADD discount_percent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD global_discount NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE sales ADD discount_authorized_by VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD discount_authorized_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE electronic_documents DROP discount_total');
        $this->addSql('ALTER TABLE roles DROP max_discount_percent');
        $this->addSql('ALTER TABLE sale_items DROP discount_percent');
        $this->addSql('ALTER TABLE sales DROP global_discount');
        $this->addSql('ALTER TABLE sales DROP discount_authorized_by');
        $this->addSql('ALTER TABLE sales DROP discount_authorized_at');
    }
}
