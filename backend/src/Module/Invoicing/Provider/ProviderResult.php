<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Provider;

/**
 * Resultado normalizado de la emisión ante SUNAT, independiente del proveedor.
 *
 * `status` refleja el estado real: ACEPTADO / RECHAZADO / PENDIENTE. Las boletas
 * suelen quedar PENDIENTE (se envían en el resumen diario) hasta que SUNAT las
 * procese; mientras no haya un proveedor real conectado, todo queda PENDIENTE.
 */
final class ProviderResult
{
    public const ACCEPTED = 'ACEPTADO';
    public const REJECTED = 'RECHAZADO';
    public const PENDING = 'PENDIENTE';

    public function __construct(
        public readonly string $status,
        public readonly ?string $hash,
        public readonly ?string $qrData,
        public readonly ?string $xml,
        public readonly ?string $cdr,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = [],
        // Enlaces que hospeda el proveedor (NubeFact devuelve URLs de PDF/XML/CDR).
        public readonly ?string $pdfUrl = null,
        public readonly ?string $xmlUrl = null,
        public readonly ?string $cdrUrl = null,
    ) {
    }
}
