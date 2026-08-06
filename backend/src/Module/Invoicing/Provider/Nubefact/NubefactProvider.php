<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Provider\Nubefact;

use App\Module\Invoicing\Entity\ElectronicDocument;
use App\Module\Invoicing\Provider\ElectronicInvoiceProviderInterface;
use App\Module\Invoicing\Provider\ProviderResult;
use App\Module\Sales\Entity\SaleItem;
use App\Shared\Settings\Service\SettingsService;
use Psr\Log\LoggerInterface;

/**
 * Adaptador de NubeFact (PSE/OSE). ÚNICO punto que conoce el formato de NubeFact;
 * el resto del ERP usa la interfaz. Envía Boletas (03) y Facturas (01) — NC/ND y
 * Guías se añaden como métodos/adaptadores sobre esta misma base.
 *
 * NubeFact firma el XML, lo envía a SUNAT y devuelve el resultado + los enlaces
 * de PDF/XML/CDR. Comunicación por cURL nativo (sin dependencias nuevas), igual
 * que el cliente de APISPERU.
 *
 * IMPORTANTE: el mapeo del JSON sigue la API documentada de NubeFact. Debe
 * validarse de extremo a extremo en el ambiente DEMO antes de producción; el
 * método buildPayload() es el punto único a ajustar si algún campo difiere.
 */
final class NubefactProvider implements ElectronicInvoiceProviderInterface
{
    /** Mapa tipo SUNAT (nuestro) → tipo de comprobante NubeFact. */
    private const DOC_TYPE_MAP = ['01' => 1, '03' => 2, '07' => 3, '08' => 4];

    /** Mapa tipo de documento del cliente → catálogo SUNAT nº 6 (NubeFact). */
    private const CLIENT_DOC_MAP = ['RUC' => '6', 'DNI' => '1', 'CE' => '4', 'PASAPORTE' => '7'];

    public function __construct(
        private readonly NubefactConfig $config,
        private readonly SettingsService $settings,
        private readonly LoggerInterface $sunatLogger,
    ) {
    }

    public function send(ElectronicDocument $document): ProviderResult
    {
        if (!$this->config->isConfigured()) {
            throw new \RuntimeException('NubeFact no está configurado (falta ruta o token en .env.local).');
        }
        if (!\function_exists('curl_init')) {
            throw new \RuntimeException('El servidor no tiene cURL disponible.');
        }

        $payload = $this->buildPayload($document);
        $response = $this->post($payload);

        return $this->mapResponse($response);
    }

    public function consult(ElectronicDocument $document): ProviderResult
    {
        if (!$this->config->isConfigured()) {
            throw new \RuntimeException('NubeFact no está configurado (falta ruta o token en .env.local).');
        }
        if (!\function_exists('curl_init')) {
            throw new \RuntimeException('El servidor no tiene cURL disponible.');
        }

        // Operación de consulta: solo necesita tipo + serie + número.
        $response = $this->post([
            'operacion' => 'consultar_comprobante',
            'tipo_de_comprobante' => self::DOC_TYPE_MAP[$document->getDocType()] ?? 2,
            'serie' => $document->getSeries(),
            'numero' => $document->getCorrelative(),
        ]);

        return $this->mapResponse($response);
    }

    /**
     * Arma el JSON que espera NubeFact desde el comprobante y su venta.
     * Precios con IGV incluido: se calcula la base y el IGV hacia atrás y se
     * suman los ítems para que el total del encabezado reconcilie con el detalle.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(ElectronicDocument $document): array
    {
        $rate = $this->settings->igvRate(); // 0.18

        $items = [];
        $totalGravada = 0.0;
        $totalIgv = 0.0;
        $total = 0.0;

        foreach ($document->getSale()->getItems() as $item) {
            /** @var SaleItem $item */
            $qty = $item->getQuantity();
            $precioUnitario = round((float) $item->getUnitPrice(), 2);           // con IGV
            $valorUnitario = round($precioUnitario / (1 + $rate), 2);            // sin IGV
            $lineTotal = round((float) $item->getLineTotal(), 2);               // con IGV (neto de descuento)
            $baseLine = round($lineTotal / (1 + $rate), 2);
            $igvLine = round($lineTotal - $baseLine, 2);

            $totalGravada += $baseLine;
            $totalIgv += $igvLine;
            $total += $lineTotal;

            $items[] = [
                'unidad_de_medida' => 'NIU',
                'codigo' => '',
                'descripcion' => $item->getDescription(),
                'cantidad' => $qty,
                'valor_unitario' => $valorUnitario,
                'precio_unitario' => $precioUnitario,
                'descuento' => '',
                'subtotal' => $baseLine,
                'tipo_de_igv' => 1, // 1 = Gravado - Operación Onerosa
                'igv' => $igvLine,
                'total' => $lineTotal,
                'anticipo_regularizacion' => false,
            ];
        }

        return [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => self::DOC_TYPE_MAP[$document->getDocType()] ?? 2,
            'serie' => $document->getSeries(),
            'numero' => $document->getCorrelative(),
            'sunat_transaction' => 1, // 1 = Venta interna
            'cliente_tipo_de_documento' => self::CLIENT_DOC_MAP[$document->getCustomerDocType()] ?? '-',
            'cliente_numero_de_documento' => $document->getCustomerDocNumber() ?: '00000000',
            'cliente_denominacion' => $document->getCustomerName(),
            'cliente_direccion' => $document->getCustomerAddress() ?? '',
            'cliente_email' => '',
            'fecha_de_emision' => $document->getIssueDate()->format('d-m-Y'),
            'moneda' => 1, // 1 = Soles
            'porcentaje_de_igv' => round($rate * 100, 2),
            'total_gravada' => round($totalGravada, 2),
            'total_igv' => round($totalIgv, 2),
            'total' => round($total, 2),
            'enviar_automaticamente_a_la_sunat' => true,
            'enviar_automaticamente_al_cliente' => false,
            'items' => $items,
        ];
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
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $this->sunatLogger->error('NubeFact: fallo de transporte cURL', [
                'httpCode' => $status,
                'curlErrno' => $errno,
                'curlError' => $error,
                'config' => $this->config->debugSnapshot(),
            ]);

            throw new \RuntimeException('No se pudo conectar con NubeFact. Intenta reenviar.');
        }

        $data = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($data)) {
            $this->sunatLogger->error('NubeFact: respuesta no válida', ['httpCode' => $status, 'body' => is_string($body) ? substr($body, 0, 500) : '']);

            throw new \RuntimeException('NubeFact devolvió una respuesta no válida.');
        }

        return $data;
    }

    /**
     * Traduce la respuesta de NubeFact a nuestro resultado normalizado.
     *
     * @param array<string, mixed> $r
     */
    private function mapResponse(array $r): ProviderResult
    {
        // NubeFact devuelve "errors" cuando rechaza el documento por validación/SUNAT.
        if (isset($r['errors']) && $r['errors'] !== '' && $r['errors'] !== null) {
            return new ProviderResult(
                status: ProviderResult::REJECTED,
                hash: null,
                qrData: null,
                xml: null,
                cdr: null,
                errorMessage: is_string($r['errors']) ? $r['errors'] : json_encode($r['errors'], \JSON_UNESCAPED_UNICODE),
                rawResponse: $r,
            );
        }

        $accepted = ($r['aceptada_por_sunat'] ?? null) === true;
        $status = $accepted ? ProviderResult::ACCEPTED : ProviderResult::PENDING;

        return new ProviderResult(
            status: $status,
            hash: $this->str($r['codigo_hash'] ?? null),
            qrData: $this->str($r['cadena_para_codigo_qr'] ?? null),
            xml: null, // NubeFact entrega el XML por enlace, no como contenido
            cdr: null,
            errorMessage: $accepted ? null : $this->str($r['sunat_description'] ?? $r['sunat_note'] ?? null),
            rawResponse: $r,
            pdfUrl: $this->str($r['enlace_del_pdf'] ?? ($this->str($r['enlace'] ?? null) !== null ? $r['enlace'].'.pdf' : null)),
            xmlUrl: $this->str($r['enlace_del_xml'] ?? null),
            cdrUrl: $this->str($r['enlace_del_cdr'] ?? null),
        );
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
