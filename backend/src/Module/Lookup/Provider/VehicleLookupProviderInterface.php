<?php

declare(strict_types=1);

namespace App\Module\Lookup\Provider;

use App\Module\Lookup\Dto\VehicleResult;
use App\Module\Lookup\Exception\LookupException;

/**
 * Abstracción del proveedor de consulta vehicular por placa. El ERP depende
 * SOLO de esta interfaz, nunca de Factiliza ni de ningún proveedor concreto.
 * Para cambiar de proveedor basta con implementarla y cambiar el alias en
 * services.yaml, sin tocar el resto del sistema.
 */
interface VehicleLookupProviderInterface
{
    /** Nombre del proveedor activo (trazabilidad/logs). */
    public function name(): string;

    /**
     * Consulta un vehículo por su placa.
     *
     * @throws LookupException ante error de conexión, timeout, credenciales,
     *                         límite de uso, respuesta inválida o no encontrado.
     */
    public function lookupVehicle(string $placa): VehicleResult;
}
