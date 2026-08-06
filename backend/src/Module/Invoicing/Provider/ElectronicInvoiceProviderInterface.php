<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Provider;

use App\Module\Invoicing\Entity\ElectronicDocument;

/**
 * Abstracción del proveedor de facturación electrónica (decisión #2 aprobada).
 *
 * Implementaciones previstas:
 *  - SimulatedProvider: desarrollo/pruebas (activa por defecto).
 *  - Adaptador PSE/OSE real (NubeFact, APISUNAT, etc.): se implementará al
 *    contratar el proveedor (pendiente P1), sin tocar los módulos de negocio.
 */
interface ElectronicInvoiceProviderInterface
{
    /** Envía el comprobante a SUNAT (vía el proveedor) y devuelve el resultado. */
    public function send(ElectronicDocument $document): ProviderResult;

    /**
     * Consulta el estado real de un comprobante ya registrado en el proveedor
     * (recupera estado + enlaces PDF/XML/CDR cuando quedó desincronizado).
     */
    public function consult(ElectronicDocument $document): ProviderResult;
}
