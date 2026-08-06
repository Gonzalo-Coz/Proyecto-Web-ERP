<?php

declare(strict_types=1);

namespace App\Module\Payment\Provider;

use App\Module\Payment\Entity\PaymentTransaction;

/**
 * Abstracción de la pasarela de pago (Adición A6 · §24.8).
 *
 * Mismo patrón que el proveedor SUNAT (decisión #2): la lógica de negocio
 * depende de esta interfaz, nunca de una pasarela concreta. El adaptador activo
 * se elige por alias en services.yaml.
 *
 * Implementaciones previstas:
 *  - ManualGateway: v1, registro estructurado con validación manual (activa).
 *  - Adaptadores reales (Izipay, Niubiz, Culqi, Mercado Pago…): al contratar
 *    una pasarela agregadora, sin tocar los módulos de negocio.
 *
 * NOTA CONSULTOR: BCP/BBVA/Yape/Plin no exponen API directa a comercios; la vía
 * real es una pasarela agregadora. Hasta contratarla, v1 registra la operación
 * (nº de operación, medio, monto) y un operador la valida manualmente.
 */
interface PaymentGatewayInterface
{
    /** Nombre del adaptador (para trazabilidad en la transacción). */
    public function name(): string;

    /**
     * Procesa/registra el intento de pago y devuelve su resultado.
     * En v1 (manual) devuelve PENDING para validación posterior.
     */
    public function authorize(PaymentTransaction $transaction): GatewayResult;
}
