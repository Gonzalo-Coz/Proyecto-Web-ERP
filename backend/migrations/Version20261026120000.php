<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Taller independiente del inventario: el taller ya no cambia el estado de la
 * moto. Las unidades que quedaron en EN_TALLER por la lógica anterior se
 * devuelven al estado real de inventario: VENDIDA si tienen venta completada,
 * si no DISPONIBLE. (No se toca el catálogo de estados; solo se corrigen datos.)
 */
final class Version20261026120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Desatasca unidades en EN_TALLER: VENDIDA si vendidas, si no DISPONIBLE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE motorcycle_units u
             SET status = CASE WHEN EXISTS (
                    SELECT 1 FROM sale_items si JOIN sales s ON s.id = si.sale_id
                    WHERE si.motorcycle_unit_id = u.id
                      AND si.item_type = 'MOTORCYCLE_UNIT' AND s.status = 'COMPLETADA'
                 ) THEN 'VENDIDA' ELSE 'DISPONIBLE' END
             WHERE u.status = 'EN_TALLER'",
        );
    }

    public function down(Schema $schema): void
    {
        // No se revierte.
    }
}
