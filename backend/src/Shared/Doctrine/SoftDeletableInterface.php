<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

/**
 * Marca una entidad como eliminable lógicamente (§23.7 del Documento Maestro).
 * Las entidades que la implementen quedan cubiertas por el filtro global
 * SoftDeleteFilter: los registros con deleted_at nunca aparecen en consultas.
 */
interface SoftDeletableInterface
{
    public function isDeleted(): bool;

    public function getDeletedAt(): ?\DateTimeImmutable;

    public function markDeleted(): void;

    public function restore(): void;
}
