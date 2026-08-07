<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Obtiene el tipo de cambio SUNAT (venta) para una fecha, para costear en
 * soles las facturas de Yamaha emitidas en dólares.
 *
 * Fuente configurable por variables de entorno (opcionales):
 *   EXCHANGE_RATE_URL   base del API (por defecto apis.net.pe)
 *   EXCHANGE_RATE_TOKEN token Bearer del API
 *
 * Si no hay token o el servicio falla, devuelve null y el usuario ingresa el
 * T.C. manualmente en la vista previa (el flujo nunca se bloquea).
 */
final class ExchangeRateClient
{
    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'default::EXCHANGE_RATE_URL')]
        private readonly ?string $baseUrl = null,
        #[Autowire(env: 'default::EXCHANGE_RATE_TOKEN')]
        private readonly ?string $token = null,
    ) {
    }

    /** Compatibilidad: solo el tipo de cambio VENTA. */
    public function saleRate(string $date): ?float
    {
        return $this->rates($date)['sell'] ?? null;
    }

    /**
     * Tipo de cambio SUNAT (compra y venta) para la fecha, o null si no disponible.
     *
     * @return array{buy: ?float, sell: float, date: string}|null
     */
    public function rates(string $date): ?array
    {
        if ($this->token === null || $this->token === '' || !\function_exists('curl_init')) {
            return null;
        }

        $base = $this->baseUrl !== null && $this->baseUrl !== '' ? rtrim($this->baseUrl, '/') : 'https://api.decolecta.com';
        // Decolecta: /v1/tipo-cambio/sunat (Bearer token) → devuelve el T.C. del día.
        $url = sprintf('%s/v1/tipo-cambio/sunat', $base);
        if ($date !== '' && $date !== date('Y-m-d')) {
            $url .= '?date='.rawurlencode($date);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 8,
            \CURLOPT_CONNECTTIMEOUT => 5,
            \CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer '.$this->token],
            \CURLOPT_USERAGENT => 'YIGM-ERP/1.0',
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $status !== 200 || !is_string($body)) {
            $this->logger->warning('Tipo de cambio: no disponible', ['status' => $status, 'errno' => $errno]);

            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->logger->warning('Tipo de cambio: respuesta no JSON', ['body' => substr($body, 0, 200)]);

            return null;
        }
        if (array_is_list($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $sell = $data['sell_price'] ?? $data['precio_venta'] ?? $data['venta'] ?? $data['sale'] ?? null;
        $buy = $data['buy_price'] ?? $data['precio_compra'] ?? $data['compra'] ?? $data['buy'] ?? null;

        if ($sell === null || (float) $sell <= 0) {
            $this->logger->warning('Tipo de cambio: campo de venta no encontrado', ['keys' => implode(',', array_keys($data))]);

            return null;
        }

        return [
            'buy' => $buy !== null && (float) $buy > 0 ? round((float) $buy, 3) : null,
            'sell' => round((float) $sell, 3),
            'date' => (string) ($data['date'] ?? $date),
        ];
    }
}
