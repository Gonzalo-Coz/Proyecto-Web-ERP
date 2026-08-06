<?php

declare(strict_types=1);

namespace App\Module\Lookup\Infrastructure\ApisPeru;

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
 * Adaptador de APISPERU (https://dniruc.apisperu.com). Implementa la interfaz
 * de proveedor usando cURL nativo (sin dependencias externas). Es el ÚNICO
 * punto que conoce el formato de APISPERU; el resto del ERP usa la interfaz.
 *
 * Manejo de fallos: conexión, timeout, credenciales (401/403), no encontrado
 * (404/422), límite de uso (429), servidor caído (5xx) y respuesta inválida.
 * Registra el error real de transporte (errno + mensaje de cURL) para diagnóstico.
 */
final class ApisPeruClient implements DocumentLookupProviderInterface
{
    public function __construct(
        private readonly ApisPeruConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function name(): string
    {
        return 'apisperu';
    }

    public function lookupPerson(string $dni): PersonResult
    {
        $data = $this->get('/dni/'.$dni);

        if (!isset($data['nombres'])) {
            $this->failMissing($data, sprintf('No se encontró la persona con DNI %s.', $dni));
        }

        return new PersonResult(
            dni: (string) ($data['dni'] ?? $dni),
            nombres: (string) $data['nombres'],
            apellidoPaterno: (string) ($data['apellidoPaterno'] ?? ''),
            apellidoMaterno: (string) ($data['apellidoMaterno'] ?? ''),
            raw: $data,
        );
    }

    public function lookupCompany(string $ruc): CompanyResult
    {
        $data = $this->get('/ruc/'.$ruc);

        if (!isset($data['razonSocial'])) {
            $this->failMissing($data, sprintf('No se encontró la empresa con RUC %s.', $ruc));
        }

        return new CompanyResult(
            ruc: (string) ($data['ruc'] ?? $ruc),
            razonSocial: (string) $data['razonSocial'],
            nombreComercial: $this->nullableStr($data['nombreComercial'] ?? null),
            estado: $this->nullableStr($data['estado'] ?? null),
            condicion: $this->nullableStr($data['condicion'] ?? null),
            direccion: $this->nullableStr($data['direccion'] ?? null),
            departamento: $this->nullableStr($data['departamento'] ?? null),
            provincia: $this->nullableStr($data['provincia'] ?? null),
            distrito: $this->nullableStr($data['distrito'] ?? null),
            // APISPERU (plan gratuito) no entrega actividad económica/CIIU.
            actividadEconomica: null,
            raw: $data,
        );
    }

    /**
     * Ejecuta la petición GET y devuelve el JSON decodificado, o lanza la
     * excepción de dominio correspondiente según el fallo.
     *
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        if (!$this->config->hasToken()) {
            throw new LookupAuthException('El servicio de consultas de documentos no está configurado (falta el token).');
        }
        if (!\function_exists('curl_init')) {
            throw new LookupUnavailableException('El servidor no tiene cURL disponible para las consultas externas.');
        }

        $url = sprintf('%s%s?token=%s', $this->config->baseUrl(), $path, rawurlencode($this->config->token()));

        $options = [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => $this->config->timeout(),
            \CURLOPT_CONNECTTIMEOUT => min(5, $this->config->timeout()),
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_HTTPHEADER => ['Accept: application/json'],
            \CURLOPT_USERAGENT => 'YIGM-ERP/1.0',
        ];
        // CA bundle explícito si se configuró (evita el fallo de verificación TLS
        // en Windows PHP sin curl.cainfo), manteniendo la verificación activa.
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
            // Diagnóstico: registra el error REAL de transporte antes de encapsular.
            $this->logger->error('APISPERU: fallo de transporte cURL', [
                'url' => $this->maskUrl($url),
                'method' => 'GET',
                'httpCode' => $status,
                'curlErrno' => $errno,
                'curlError' => $error,
                'config' => $this->config->debugSnapshot(),
            ]);

            // Mensaje limpio de cara al usuario. El detalle técnico (errno +
            // curlError) queda en el log de arriba para diagnóstico.
            throw new LookupUnavailableException(
                \CURLE_OPERATION_TIMEDOUT === $errno
                    ? 'La consulta al proveedor tardó demasiado (timeout). Intenta nuevamente.'
                    : 'No se pudo conectar con el proveedor de consultas. Intenta nuevamente.',
            );
        }

        return $this->mapStatus($status, is_string($body) ? $body : '');
    }

    /** Oculta el token en la URL para no filtrarlo a los logs. */
    private function maskUrl(string $url): string
    {
        return preg_replace('/token=[^&]+/', 'token=***', $url) ?? $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStatus(int $status, string $body): array
    {
        if ($status === 401 || $status === 403) {
            throw new LookupAuthException('Credenciales del proveedor inválidas. Revisa el token de APISPERU.');
        }
        if ($status === 429) {
            throw new LookupRateLimitException('Se alcanzó el límite de consultas del plan. Intenta más tarde.');
        }
        if ($status === 404 || $status === 422) {
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
     * Cuando la respuesta 200 no trae el campo esperado: distingue "no
     * encontrado" (trae message/success=false) de una respuesta corrupta.
     *
     * @param array<string, mixed> $data
     */
    private function failMissing(array $data, string $notFoundMessage): never
    {
        if (isset($data['message']) || (isset($data['success']) && $data['success'] === false)) {
            throw new DocumentNotFoundException(
                isset($data['message']) ? (string) $data['message'] : $notFoundMessage,
            );
        }

        throw new InvalidLookupResponseException('El proveedor devolvió datos incompletos.');
    }

    private function messageFrom(string $body): ?string
    {
        $data = json_decode($body, true);

        return is_array($data) && isset($data['message']) ? (string) $data['message'] : null;
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
