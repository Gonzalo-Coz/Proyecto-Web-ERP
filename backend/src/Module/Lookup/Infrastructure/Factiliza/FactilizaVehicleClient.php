<?php

declare(strict_types=1);

namespace App\Module\Lookup\Infrastructure\Factiliza;

use App\Module\Lookup\Dto\VehicleResult;
use App\Module\Lookup\Exception\DocumentNotFoundException;
use App\Module\Lookup\Exception\InvalidLookupResponseException;
use App\Module\Lookup\Exception\LookupAuthException;
use App\Module\Lookup\Exception\LookupRateLimitException;
use App\Module\Lookup\Exception\LookupUnavailableException;
use App\Module\Lookup\Provider\VehicleLookupProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Adaptador de FACTILIZA (https://api.factiliza.com) para consulta vehicular.
 * Autenticación por header Authorization: Bearer.
 *
 *  - Placa: GET /v1/placa/info/{placa}
 *    respuesta: { success, message, data:{ placa, marca, modelo, serie, color, motor, vin } }
 *
 * Es el ÚNICO punto que conoce el formato de Factiliza; el resto del ERP usa la interfaz.
 */
final class FactilizaVehicleClient implements VehicleLookupProviderInterface
{
    public function __construct(
        private readonly FactilizaConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function name(): string
    {
        return 'factiliza';
    }

    public function lookupVehicle(string $placa): VehicleResult
    {
        $data = $this->get('/v1/placa/info/'.rawurlencode($placa));

        if (!isset($data['marca']) && !isset($data['modelo'])) {
            throw new DocumentNotFoundException(sprintf('No se encontró el vehículo con placa %s.', $placa));
        }

        return new VehicleResult(
            placa: (string) ($data['placa'] ?? $placa),
            marca: (string) ($data['marca'] ?? ''),
            modelo: (string) ($data['modelo'] ?? ''),
            color: (string) ($data['color'] ?? ''),
            serie: (string) ($data['serie'] ?? ''),
            motor: (string) ($data['motor'] ?? ''),
            vin: (string) ($data['vin'] ?? ''),
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed> el contenido de "data" de la respuesta
     */
    private function get(string $path): array
    {
        if (!$this->config->hasToken()) {
            throw new LookupAuthException('La consulta vehicular no está configurada (falta el token de Factiliza).');
        }
        if (!\function_exists('curl_init')) {
            throw new LookupUnavailableException('El servidor no tiene cURL disponible para las consultas externas.');
        }

        $url = $this->config->baseUrl().$path;
        $options = [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_NOSIGNAL => true, // necesario para que los timeouts en ms sean fiables
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer '.$this->config->token(),
            ],
            \CURLOPT_USERAGENT => 'YIGM-ERP/1.0',
            \CURLOPT_ENCODING => '', // acepta gzip: respuesta más liviana y rápida
        ];
        if ($this->config->caInfo() !== null) {
            $options[\CURLOPT_CAINFO] = $this->config->caInfo();
        }

        // UN SOLO intento, sin reintentos automáticos. Motivo: si Factiliza recibe la
        // petición y solo tarda en responder, cuenta como consulta aunque nosotros
        // cortemos por timeout; reintentar gastaría varias consultas por un mismo lookup.
        // Con un único intento, un lookup gasta como máximo UNA consulta. La fiabilidad
        // la aporta la caché en base de datos (placas repetidas ni llegan aquí).
        // Timeout de 12 s (el navegador espera hasta 30 s), suficiente para que Factiliza
        // responda en una sola llamada sin obligar a reintentar.
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            \CURLOPT_TIMEOUT_MS => 12_000,
            \CURLOPT_CONNECTTIMEOUT_MS => 4_000,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $this->logger->error('FACTILIZA: fallo de transporte', [
                'url' => $url, 'httpCode' => $status, 'curlErrno' => $errno,
                'curlError' => $error, 'config' => $this->config->debugSnapshot(),
            ]);

            throw new LookupUnavailableException(
                \CURLE_OPERATION_TIMEDOUT === $errno
                    ? 'El proveedor no respondió a tiempo. Intenta de nuevo o ingresa los datos manualmente.'
                    : 'No se pudo conectar con el proveedor de consultas. Intenta de nuevo o ingresa los datos manualmente.',
            );
        }

        return $this->mapStatus($status, is_string($body) ? $body : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStatus(int $status, string $body): array
    {
        if ($status === 401 || $status === 403) {
            throw new LookupAuthException('Credenciales del proveedor inválidas. Revisa el token de Factiliza.');
        }
        if ($status === 429) {
            throw new LookupRateLimitException('Se alcanzó el límite de consultas del plan de Factiliza. Intenta más tarde.');
        }
        if ($status === 404 || $status === 422 || $status === 400) {
            throw new DocumentNotFoundException($this->messageFrom($body) ?? 'Placa no encontrada.');
        }
        if ($status >= 500 || $status === 0) {
            throw new LookupUnavailableException('El proveedor de consultas no está disponible. Intenta más tarde.');
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new InvalidLookupResponseException('El proveedor devolvió una respuesta no válida.');
        }
        // Formato Factiliza: { success, message, data:{...} }.
        if (isset($json['success']) && $json['success'] === false) {
            throw new DocumentNotFoundException((string) ($json['message'] ?? 'Placa no encontrada.'));
        }
        $data = $json['data'] ?? $json;

        return is_array($data) ? $data : [];
    }

    private function messageFrom(string $body): ?string
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        return isset($data['message']) ? (string) $data['message'] : null;
    }
}
