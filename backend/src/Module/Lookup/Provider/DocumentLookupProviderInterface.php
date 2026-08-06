<?php

declare(strict_types=1);

namespace App\Module\Lookup\Provider;

use App\Module\Lookup\Dto\CompanyResult;
use App\Module\Lookup\Dto\PersonResult;
use App\Module\Lookup\Exception\LookupException;

/**
 * Abstracción del proveedor de consultas de documentos (Adición / integración
 * de APIs externas). El ERP depende SOLO de esta interfaz, nunca de APISPERU
 * ni de ningún proveedor concreto (mismo patrón que SUNAT y la pasarela de pago).
 *
 * Para cambiar de proveedor (RENIEC, SUNAT, otro) basta con implementar esta
 * interfaz y cambiar el alias en services.yaml, sin tocar el resto del sistema.
 */
interface DocumentLookupProviderInterface
{
    /** Nombre del proveedor activo (trazabilidad/logs). */
    public function name(): string;

    /**
     * Consulta una persona por DNI (8 dígitos).
     *
     * @throws LookupException ante error de conexión, timeout, credenciales,
     *                         límite de uso, respuesta inválida o no encontrado.
     */
    public function lookupPerson(string $dni): PersonResult;

    /**
     * Consulta una empresa por RUC (11 dígitos).
     *
     * @throws LookupException (ver lookupPerson).
     */
    public function lookupCompany(string $ruc): CompanyResult;
}
