<?php

declare(strict_types=1);

namespace App\Module\Lookup\Dto;

/**
 * Resultado normalizado de una consulta por RUC (agnóstico del proveedor).
 *
 * `actividadEconomica` es opcional: APISPERU (plan gratuito) no la entrega; un
 * proveedor futuro (SUNAT) podría rellenarla sin cambiar esta estructura.
 */
final class CompanyResult
{
    public function __construct(
        public readonly string $ruc,
        public readonly string $razonSocial,
        public readonly ?string $nombreComercial,
        public readonly ?string $estado,
        public readonly ?string $condicion,
        public readonly ?string $direccion,
        public readonly ?string $departamento,
        public readonly ?string $provincia,
        public readonly ?string $distrito,
        public readonly ?string $actividadEconomica = null,
        /** @var array<string, mixed> Respuesta cruda del proveedor. */
        public readonly array $raw = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ruc' => $this->ruc,
            'razonSocial' => $this->razonSocial,
            'nombreComercial' => $this->nombreComercial,
            'estado' => $this->estado,
            'condicion' => $this->condicion,
            'direccion' => $this->direccion,
            'departamento' => $this->departamento,
            'provincia' => $this->provincia,
            'distrito' => $this->distrito,
            'actividadEconomica' => $this->actividadEconomica,
        ];
    }
}
