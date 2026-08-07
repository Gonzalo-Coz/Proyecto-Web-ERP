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
        $storedDate = (string) $this->settings->get('tax.exchange_rate_date');

        if ($storedDate === $today && $stored !== '') {
            return $this->payload($today, (float) $stored, 'guardado');
        }

        $rate = $this->client->saleRate($today);
        if ($rate !== null) {
            $this->store($today, $rate);

            return $this->payload($today, $rate, 'sunat');
        }

        if ($stored !== '') {
            return $this->payload($storedDate, (float) $stored, 'anterior', true);
        }

        return $this->payload($today, null, 'ninguno');
    }

    /** Guarda manualmente el T.C. del día. */
    #[Route('', name: 'exchange_rate_set', methods: ['PUT'])]
    public function set(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $rate = (float) ($data['rate'] ?? 0);
        if ($rate <= 0 || $rate > 100) {
            throw new UnprocessableEntityHttpException('Tipo de cambio inválido.');
        }
        $today = date('Y-m-d');
        $this->store($today, $rate);

        return $this->payload($today, $rate, 'manual');
    }

    private function store(string $date, float $rate): void
    {
        $this->settings->update([
            'tax.exchange_rate' => number_format($rate, 3, '.', ''),
            'tax.exchange_rate_date' => $date,
        ]);
    }

    private function payload(string $date, ?float $rate, string $source, bool $stale = false): JsonResponse
    {
        return new JsonResponse([
            'date' => $date,
            'rate' => $rate,
            'source' => $source,   // sunat | guardado | manual | anterior | ninguno
            'stale' => $stale,     // true = no es de hoy (revisar)
        ]);
    }
}
