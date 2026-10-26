<?php

declare(strict_types=1);

namespace App\Module\Lookup\Dto;

/**
 * Resultado normalizado de una consulta vehicular por placa (agnóstico del
 * proveedor). Cualquier proveedor (Factiliza hoy; otro en el futuro) mapea su
 * respuesta a esta estructura, de modo que el ERP no dependa del proveedor.
 *
 * Solo datos TÉCNICOS del vehículo (no el titular), para cumplir la Ley 29733.
 */
final class VehicleResult
{
    public function __construct(
        public readonly string $placa,
        public readonly string $marca,
        public readonly string $modelo,
        public readonly string $color,
        public readonly string $serie,
        public readonly string $motor,
        public readonly string $vin,
        /** @var array<string, mixed> Respuesta cruda del proveedor (trazabilidad). */
        public readonly array $raw = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'placa' => $this->placa,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'color' => $this->color,
            'serie' => $this->serie,
            'motor' => $this->motor,
            'vin' => $this->vin,
        ];
    }
}
