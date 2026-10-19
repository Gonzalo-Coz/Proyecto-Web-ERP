<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Taller: flujo simplificado a 3 etapas. Mapea los estados antiguos
 * (EN_DIAGNOSTICO, ESPERANDO_REPUESTOS, LISTA_PARA_ENTREGA, GARANTIA) a los nuevos.
 */
final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Simplifica los estados de service_orders a RECIBIDA / EN_REPARACION / ENTREGADA / ANULADA';
    }

    public function up(Schema $schema): void
    {
        // Diagnóstico, esperando repuestos y lista para entrega → En trabajo.
        $this->addSql("UPDATE service_orders SET status = 'EN_REPARACION' WHERE status IN ('EN_DIAGNOSTICO', 'ESPERANDO_REPUESTOS', 'LISTA_PARA_ENTREGA')");
        // Garantía → Entregada (ya pasó por el taller).
        $this->addSql("UPDATE service_orders SET status = 'ENTREGADA' WHERE status = 'GARANTIA'");
    }

    public function down(Schema $schema): void
    {
        // No se revierte: los estados antiguos granulares no se pueden reconstruir.
    }
}
