<?php

declare(strict_types=1);

namespace App\Module\Dispatch\Service;

use App\Module\Dispatch\Entity\DispatchGuide;
use App\Module\Invoicing\Provider\Nubefact\NubefactConfig;
use Psr\Log\LoggerInterface;

/**
 * Emite/consulta Guías de Remisión Remitente en NubeFact (operación generar_guia).
 * ÚNICO punto que conoce el formato GRE de NubeFact. Validar en DEMO antes de
 * producción: los nombres de campo siguen la API documentada de NubeFact.
 */
final class NubefactGuideClient
{
    /** Catálogo 6 (tipo de documento del destinatario/conductor). */
    private const DOC_MAP = ['RUC' => '6', 'DNI' => '1', 'CE' => '4', 'PASAPORTE' => '7', 'OTRO' => '0'];

    public function __construct(
        private readonly NubefactConfig $config,
        private readonly LoggerInterface $sunatLogger,
    ) {
    }

    /** @return array<string, mixed> resultado normalizado */
    public function emit(DispatchGuide $guide): array
    {
        if (!$this->config->isConfigured()) {
            throw new \RuntimeException('NubeFact no está configurado (falta ruta o token).');
        }

        return $this->mapResponse($this->post($this->buildPayload($guide)));
    }

    /** @return array<string, mixed> resultado normalizado */
    public function consult(DispatchGuide $guide): array
    {
        $response = $this->post([
            'operacion' => 'consultar_guia',
            'tipo_de_comprobante' => 7, // 7 = Guía de Remisión Remitente
            'serie' => $guide->getSeries(),
            'numero' => $guide->getCorrelative(),
        ]);

        return $this->mapResponse($response);
    }

    /**
     * Arma el JSON de `generar_guia` desde la guía. Público para poder probarlo
     * unitariamente (no toca red ni config).
     *
     * @return array<string, mixed>
     */
    public function buildPayload(DispatchGuide $guide): array
    {
        $items = [];
        foreach ($guide->getItems() as $it) {
            $items[] = [
                'unidad_de_medida' => (string) ($it['unidad'] ?? 'NIU'),
                'codigo' => (string) ($it['codigo'] ?? ''),
                'descripcion' => (string) ($it['descripcion'] ?? ''),
                'cantidad' => (float) ($it['cantidad'] ?? 1),
            ];
        }

        $privado = $guide->getTransportMode() === '02';

        $payload = [
            'operacion' => 'generar_guia',
            'tipo_de_comprobante' => 7, // Guía de Remisión Remitente
            'serie' => $guide->getSeries(),
            'numero' => $guide->getCorrelative(),
            'cliente_tipo_de_documento' => self::DOC_MAP[$guide->getRecipientDocType()] ?? '1',
            'cliente_numero_de_documento' => $guide->getRecipientDocNumber(),
            'cliente_denominacion' => $guide->getRecipientName(),
            'cliente_direccion' => $guide->getDestinationAddress(),
            'cliente_email' => '',
            'fecha_de_emision' => $guide->getIssueDate()->format('d-m-Y'),
            'observaciones' => $guide->getObservations() ?? '',
            'motivo_de_traslado' => $guide->getMotive(),
            'peso_bruto_total' => (float) $guide->getTotalWeight(),
            'peso_bruto_unidad_de_medida' => $guide->getWeightUnit(),
            'numero_de_bultos' => $guide->getPackages(),
            'tipo_de_transporte' => $guide->getTransportMode(), // 01 público, 02 privado
            'fecha_de_inicio_de_traslado' => $guide->getTransferDate()->format('d-m-Y'),
            'punto_de_partida_ubigeo' => $guide->getOriginUbigeo() ?? '',
            'punto_de_partida_direccion' => $guide->getOriginAddress(),
            'punto_de_llegada_ubigeo' => $guide->getDestinationUbigeo() ?? '',
            'punto_de_llegada_direccion' => $guide->getDestinationAddress(),
            'enviar_automaticamente_a_la_sunat' => true,
            'items' => $items,
        ];

        if ($privado) {
            // Transporte privado: vehículo + conductor.
            $payload['vehiculo_placa_numero'] = $guide->getVehiclePlate() ?? '';
            $payload['conductor_documento_tipo'] = '1';
            $payload['conductor_documento_numero'] = '';
            $payload['conductor_nombre'] = $guide->getDriverName() ?? '';
            $payload['conductor_apellidos'] = '';
            $payload['conductor_numero_licencia'] = $guide->getDriverLicense() ?? '';
        } else {
            // Transporte público: empresa transportista.
            $payload['transportista_documento_tipo'] = '6';
            $payload['transportista_documento_numero'] = $guide->getCarrierRuc() ?? '';
            $payload['transportista_denominacion'] = $guide->getCarrierName() ?? '';
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(array $payload): array
    {
        $ch = curl_init($this->config->url());
        $options = [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => json_encode($payload, \JSON_UNESCAPED_UNICODE),
            \CURLOPT_TIMEOUT => $this->config->timeout(),
            \CURLOPT_CONNECTTIMEOUT => min(8, $this->config->timeout()),
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                sprintf('Authorization: Token token="%s"', $this->config->token()),
            ],
            \CURLOPT_USERAGENT => 'YIGM-ERP/1.0',
        ];
        if ($this->config->caInfo() !== null) {
            $options[\CURLOPT_CAINFO] = $this->config->caInfo();
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException('No se pudo conectar con NubeFact para la guía.');
        }

        $data = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($data)) {
            throw new \RuntimeException('NubeFact devolvió una respuesta no válida para la guía.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return array{status: string, hash: ?string, qrData: ?string, pdfUrl: ?string, xmlUrl: ?string, errorMessage: ?string, raw: array<string, mixed>}
     */
    private function mapResponse(array $r): array
    {
        if (isset($r['errors']) && $r['errors'] !== '' && $r['errors'] !== null) {
            return [
                'status' => 'RECHAZADO',
                'hash' => null, 'qrData' => null, 'pdfUrl' => null, 'xmlUrl' => null,
                'errorMessage' => is_string($r['errors']) ? $r['errors'] : json_encode($r['errors'], \JSON_UNESCAPED_UNICODE),
                'raw' => $r,
            ];
        }

        $accepted = ($r['aceptada_por_sunat'] ?? null) === true || ($r['aceptada_por_sunat'] ?? null) === 'true';

        return [
            'status' => $accepted ? 'ACEPTADO' : 'PENDIENTE',
            'hash' => $this->str($r['codigo_hash'] ?? null),
            'qrData' => $this->str($r['cadena_para_codigo_qr'] ?? null),
            'pdfUrl' => $this->str($r['enlace_del_pdf'] ?? null),
            'xmlUrl' => $this->str($r['enlace_del_xml'] ?? null),
            'errorMessage' => $accepted ? null : $this->str($r['sunat_description'] ?? $r['sunat_note'] ?? null),
            'raw' => $r,
        ];
    }

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
