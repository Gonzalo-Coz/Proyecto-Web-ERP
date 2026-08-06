<?php

declare(strict_types=1);

namespace App\Module\Payment\Provider;

use App\Module\Payment\Entity\PaymentTransaction;

/**
 * Pasarela MANUAL (v1 · Adición A6): no llama a ningún servicio externo.
 * Registra la operación como PENDIENTE para que un operador la valide contra
 * el comprobante de la app bancaria / voucher. No tiene integración real.
 *
 * Se reemplaza por un adaptador de agregador (Izipay/Niubiz/Culqi/Mercado Pago)
 * cambiando SOLO el alias en services.yaml, sin tocar los módulos de negocio.
 */
final class ManualGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function authorize(PaymentTransaction $transaction): GatewayResult
    {
        return new GatewayResult(
            status: 'PENDING',
            operationNumber: $transaction->getOperationNumber(),
            message: 'Registro manual: pendiente de validación por un operador.',
            raw: ['gateway' => 'manual', 'mode' => 'manual_validation'],
        );
    }
}
