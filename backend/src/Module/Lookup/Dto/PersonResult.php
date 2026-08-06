<?php

declare(strict_types=1);

namespace App\Module\Lookup\Dto;

/**
 * Resultado normalizado de una consulta por DNI (agnóstico del proveedor).
 *
 * Cualquier proveedor (APISPERU hoy; RENIEC/SUNAT en el futuro) debe mapear su
 * respuesta a esta estructura, de modo que el ERP no dependa del proveedor.
 */
final class PersonResult
{
    public function __construct(
        public readonly string $dni,
        public readonly string $nombres,
        public readonly string $apellidoPaterno,
        public readonly string $apellidoMaterno,
        /** @var array<string, mixed> Respuesta cruda del proveedor (trazabilidad/depuración). */
        public readonly array $raw = [],
    ) {
    }

    public function nombreCompleto(): string
    {
        return trim(sprintf('%s %s %s', $this->nombres, $this->apellidoPaterno, $this->apellidoMaterno));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'dni' => $this->dni,
            'nombres' => $this->nombres,
            'apellidoPaterno' => $this->apellidoPaterno,
            'apellidoMaterno' => $this->apellidoMaterno,
            'nombreCompleto' => $this->nombreCompleto(),
        ];
    }
}
