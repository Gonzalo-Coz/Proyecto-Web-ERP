<?php

declare(strict_types=1);

namespace App\Module\Lookup\Infrastructure\Decolecta;

use App\Module\Lookup\Dto\CompanyResult;
use App\Module\Lookup\Dto\PersonResult;
use App\Module\Lookup\Exception\DocumentNotFoundException;
use App\Module\Lookup\Exception\InvalidLookupResponseException;
use App\Module\Lookup\Exception\LookupAuthException;
use App\Module\Lookup\Exception\LookupRateLimitException;
use App\Module\Lookup\Exception\LookupUnavailableException;
use App\Module\Lookup\Provider\DocumentLookupProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Adaptador de DECOLECTA (https://api.decolecta.com). Implementa la interfaz de
 * proveedor usando cURL nativo. Autenticación por header Authorization: Bearer.
 *
 *  - DNI: GET /v1/reniec/dni?numero=  → first_name, first_last_name, second_last_name
 *  - RUC: GET /v1/sunat/ruc?numero=   → razon_social, estado, condicion, direccion...
 *
 * Es el ÚNICO punto que conoce el formato de Decolecta; el resto del ERP usa la interfaz.
 */
final class DecolectaClient implements DocumentLookupProviderInterface
{
    public function __construct(
        private readonly DecolectaConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function name(): string
    {
        return 'decolecta';
    }

    public function lookupPerson(string $dni): PersonResult
    {
        $data = $this->get('/v1/reniec/dni', ['numero' => $dni]);

        if (!isset($data['first_name']) && !isset($data['full_name'])) {
            $this->failMissing($data, sprintf('No se encontró la persona con DNI %s.', $dni));
        }

        return new PersonResult(
            dni: (string) ($data['document_number'] ?? $dni),
            nombres: (string) ($data['first_name'] ?? ''),
            apellidoPaterno: (string) ($data['first_last_name'] ?? ''),
            apellidoMaterno: (string) ($data['second_last_name'] ?? ''),
            raw: $data,
        );
    }

    public function lookupCompany(string $ruc): CompanyResult
    {
        $data = $this->get('/v1/sunat/ruc', ['numero' => $ruc]);

        if (!isset($data['razon_social'])) {
            $this->failMissing($data, sprintf('No se encontró la empresa con RUC %s.', $ruc));
        }

        return new CompanyResult(
            ruc: (string) ($data['numero_documento'] ?? $ruc),
            razonSocial: (string) $data['razon_social'],
            nombreComercial: null, // SUNAT no expone nombre comercial en el básico
            estado: $this->nullableStr($data['estado'] ?? null),
            condicion: $this->nullableStr($data['condicion'] ?? null),
            direccion: $this->nullableStr($data['direccion'] ?? null),
            departamento: $this->nullableStr($data['departamento'] ?? null),
            provincia: $this->nullableStr($data['provincia'] ?? null),
            distrito: $this->nullableStr($data['distrito'] ?? null),
            actividadEconomica: $this->nullableStr($data['actividad_economica'] ?? null),
            raw: $data,
        );
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        if (!$this->config->hasToken()) {
            throw new LookupAuthException('El servicio de consultas de documentos no está configurado (falta el token de Decolecta).');
        }
        if (!\function_exists('curl_init')) {
            throw new LookupUnavailableException('El servidor no tiene cURL disponible para las consultas externas.');
        }

        $url = $this->config->baseUrl().$path.'?'.http_build_query($query);

        $options = [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => $this->config->timeout(),
            \CURLOPT_CONNECTTIMEOUT => min(5, $this->config->timeout()),
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->config->token(),
            ],
            \CURLOPT_USERAGENT => 'YIGM-ERP/1.0',
        ];
        if ($this->config->caInfo() !== null) {
            $options[\CURLOPT_CAINFO] = $this->config->caInfo();
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $this->logger->error('DECOLECTA: fallo de transporte cURL', [
                'url' => $url,
                'method' => 'GET',
                'httpCode' => $status,
                'curlErrno' => $errno,
                'curlError' => $error,
                'config' => $this->config->debugSnapshot(),
            ]);

            throw new LookupUnavailableException(
                \CURLE_OPERATION_TIMEDOUT === $errno
                    ? 'La consulta al proveedor tardó demasiado (timeout). Intenta nuevamente.'
                    : 'No se pudo conectar con el proveedor de consultas. Intenta nuevamente.',
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
            throw new LookupAuthException('Credenciales del proveedor inválidas. Revisa el token de Decolecta.');
        }
        if ($status === 429) {
            throw new LookupRateLimitException('Se alcanzó el límite de consultas del plan. Intenta más tarde.');
        }
        if ($status === 404 || $status === 422 || $status === 400) {
            throw new DocumentNotFoundException($this->messageFrom($body) ?? 'Documento no encontrado.');
        }
        if ($status >= 500 || $status === 0) {
            throw new LookupUnavailableException('El proveedor de consultas no está disponible. Intenta más tarde.');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new InvalidLookupResponseException('El proveedor devolvió una respuesta no válida.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function failMissing(array $data, string $notFoundMessage): never
    {
        if (isset($data['message']) || isset($data['error'])) {
            throw new DocumentNotFoundException(
                (string) ($data['message'] ?? $data['error'] ?? $notFoundMessage),
            );
        }

        throw new InvalidLookupResponseException('El proveedor devolvió datos incompletos.');
    }

    private function messageFrom(string $body): ?string
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        return isset($data['message']) ? (string) $data['message'] : (isset($data['error']) ? (string) $data['error'] : null);
    }

    private function nullableStr(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
