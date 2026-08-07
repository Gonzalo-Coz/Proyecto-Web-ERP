<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Controller;

use App\Module\Purchasing\Service\ExchangeRateClient;
use App\Shared\Settings\Service\SettingsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tipo de cambio del día (SUNAT) para convertir dólares a soles.
 * Se autocompleta con SUNAT; si no está disponible, se guarda/edita manual.
 * El valor del día queda cacheado en la configuración para reusarlo.
 */
#[Route('/api/v1/exchange-rate')]
#[OA\Tag(name: 'Tipo de cambio')]
final class ExchangeRateController
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ExchangeRateClient $client,
    ) {
    }

    /** Devuelve el T.C. del día: caché → SUNAT → último guardado. */
    #[Route('', name: 'exchange_rate_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $today = date('Y-m-d');
        $stored = (string) $this->settings->get('tax.exchange_rate');
        $storedBuy = (string) $this->settings->get('tax.exchange_rate_buy');
        $storedDate = (string) $this->settings->get('tax.exchange_rate_date');

        if ($storedDate === $today && $stored !== '') {
            return $this->payload($today, (float) $stored, $storedBuy !== '' ? (float) $storedBuy : null, 'guardado');
        }

        $rates = $this->client->rates($today);
        if ($rates !== null) {
            $this->store($today, $rates['sell'], $rates['buy']);

            return $this->payload($today, $rates['sell'], $rates['buy'], 'sunat');
        }

        if ($stored !== '') {
            return $this->payload($storedDate, (float) $stored, $storedBuy !== '' ? (float) $storedBuy : null, 'anterior', true);
        }

        return $this->payload($today, null, null, 'ninguno');
    }

    /** Guarda manualmente el T.C. del día (venta y, opcional, compra). */
    #[Route('', name: 'exchange_rate_set', methods: ['PUT'])]
    public function set(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $sell = (float) ($data['rate'] ?? $data['sell'] ?? 0);
        $buy = isset($data['buy']) && $data['buy'] !== '' && $data['buy'] !== null ? (float) $data['buy'] : null;
        if ($sell <= 0 || $sell > 100) {
            throw new UnprocessableEntityHttpException('Tipo de cambio inválido.');
        }
        $today = date('Y-m-d');
        $this->store($today, $sell, $buy);

        return $this->payload($today, $sell, $buy, 'manual');
    }

    private function store(string $date, float $sell, ?float $buy): void
    {
        $this->settings->update([
            'tax.exchange_rate' => number_format($sell, 3, '.', ''),
            'tax.exchange_rate_buy' => $buy !== null ? number_format($buy, 3, '.', '') : '',
            'tax.exchange_rate_date' => $date,
        ]);
    }

    private function payload(string $date, ?float $sell, ?float $buy, string $source, bool $stale = false): JsonResponse
    {
        return new JsonResponse([
            'date' => $date,
            'rate' => $sell,       // venta (compat)
            'sell' => $sell,
            'buy' => $buy,
            'source' => $source,   // sunat | guardado | manual | anterior | ninguno
            'stale' => $stale,
        ]);
    }
}
