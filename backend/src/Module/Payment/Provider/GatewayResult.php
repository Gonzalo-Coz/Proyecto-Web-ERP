<?php

declare(strict_types=1);

namespace App\Module\Payment\Provider;

/**
 * Resultado de una operación de pasarela de pago (Adición A6).
 * Inmutable: describe cómo respondió la pasarela.
 */
final class GatewayResult
{
    public function __construct(
        public readonly string $status,          // PENDING | APPROVED | REJECTED
        public readonly ?string $operationNumber = null,
        public readonly ?string $message = null,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {
    }
}
