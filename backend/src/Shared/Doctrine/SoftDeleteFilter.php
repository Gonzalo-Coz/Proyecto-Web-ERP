<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Filtro SQL global: excluye automáticamente los registros eliminados
 * lógicamente en TODAS las consultas Doctrine (§23.7).
 * Habilitado en config/packages/doctrine.yaml (orm.filters.soft_delete).
 */
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->getReflectionClass()->implementsInterface(SoftDeletableInterface::class)) {
            return '';
        }

        return sprintf('%s.deleted_at IS NULL', $targetTableAlias);
    }
}
