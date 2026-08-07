<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Service;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Lee una factura electrónica de Yamaha (UBL 2.1 / SUNAT) y devuelve una
 * estructura normalizada: cabecera, proveedor y líneas clasificadas en
 * motocicletas (con VIN, motor, chasis, modelo, color, año) o repuestos.
 *
 * El costo unitario neto se toma del valor de venta de la línea
 * (LineExtensionAmount / cantidad), que ya descuenta las rebajas de Yamaha.
 */
final class YamahaInvoiceParser
{
    private const NS = [
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    ];

    /** @return array<string, mixed> */
    public function parse(string $xml): array
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml);
        libxml_use_internal_errors($prev);
        if ($ok === false || $doc->documentElement === null) {
            throw new UnprocessableEntityHttpException('El archivo no es un XML válido.');
        }
        if ($doc->documentElement->localName !== 'Invoice') {
            throw new UnprocessableEntityHttpException('El XML no es una factura electrónica (UBL Invoice).');
        }

        $xp = new \DOMXPath($doc);
        foreach (self::NS as $prefix => $uri) {
            $xp->registerNamespace($prefix, $uri);
        }

        $id = $this->str($xp, '/*/cbc:ID');
        [$series, $number] = array_pad(explode('-', $id, 2), 2, '');

        $supplierBase = '/*/cac:AccountingSupplierParty/cac:Party';
        $customerBase = '/*/cac:AccountingCustomerParty/cac:Party';

        $data = [
            'document' => [
                'fullNumber' => $id,
                'series' => $series,
                'number' => $number,
                'issueDate' => $this->str($xp, '/*/cbc:IssueDate'),
                'typeCode' => $this->str($xp, '/*/cbc:InvoiceTypeCode'),
                'currency' => $this->str($xp, '/*/cbc:DocumentCurrencyCode') ?: 'PEN',
            ],
            'supplier' => [
                'ruc' => $this->str($xp, $supplierBase.'/cac:PartyIdentification/cbc:ID'),
                'name' => $this->str($xp, $supplierBase.'/cac:PartyLegalEntity/cbc:RegistrationName'),
            ],
            'customer' => [
                'ruc' => $this->str($xp, $customerBase.'/cac:PartyIdentification/cbc:ID'),
                'name' => $this->str($xp, $customerBase.'/cac:PartyLegalEntity/cbc:RegistrationName'),
            ],
            'lines' => [],
        ];

        foreach ($xp->query('/*/cac:InvoiceLine') as $line) {
            $data['lines'][] = $this->parseLine($xp, $line);
        }

        if ($data['lines'] === []) {
            throw new UnprocessableEntityHttpException('La factura no tiene líneas de detalle.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function parseLine(\DOMXPath $xp, \DOMNode $line): array
    {
        $qty = (float) ($this->str($xp, 'cbc:InvoicedQuantity', $line) ?: '1');
        $qty = $qty > 0 ? $qty : 1.0;
        $lineExt = (float) ($this->str($xp, 'cbc:LineExtensionAmount', $line) ?: '0');
        $priceGross = (float) ($this->str($xp, 'cac:Price/cbc:PriceAmount', $line) ?: '0');

        // Propiedades adicionales (aquí Yamaha coloca VIN, motor, modelo, color…).
        $props = [];
        foreach ($xp->query('cac:Item/cac:AdditionalItemProperty', $line) as $p) {
            $name = $this->str($xp, 'cbc:Name', $p);
            $value = $this->str($xp, 'cbc:Value', $p);
            if ($name !== '') {
                $props[mb_strtolower($name)] = $value;
            }
        }

        $vin = $this->prop($props, ['vin']);
        $isMoto = $vin !== '' || $this->prop($props, ['motor']) !== '';

        $netUnit = $qty > 0 ? round($lineExt / $qty, 2) : $lineExt;

        $out = [
            'kind' => $isMoto ? 'MOTORCYCLE' : 'SPARE_PART',
            'code' => $this->str($xp, 'cac:Item/cac:SellersItemIdentification/cbc:ID', $line),
            'description' => $this->str($xp, 'cac:Item/cbc:Description', $line),
            'quantity' => $qty,
            'unitPriceGross' => $priceGross,
            'netUnit' => $netUnit,
            'lineExtension' => $lineExt,
        ];

        if ($isMoto) {
            $out['moto'] = [
                'brand' => $this->prop($props, ['marca']) ?: 'YAMAHA',
                'model' => $this->prop($props, ['modelo']),
                'color' => $this->prop($props, ['color']),
                'engine' => $this->prop($props, ['motor']),
                'vin' => $vin,
                'chassis' => $this->prop($props, ['serie/chasis', 'serie', 'chasis']) ?: $vin,
                'year' => $this->prop($props, ['año modelo', 'anio modelo', 'ano modelo']),
            ];
        }

        return $out;
    }

    /** @param array<string,string> $props */
    private function prop(array $props, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($props[$k]) && trim($props[$k]) !== '') {
                return trim($props[$k]);
            }
        }

        return '';
    }

    private function str(\DOMXPath $xp, string $path, ?\DOMNode $ctx = null): string
    {
        $node = $ctx !== null ? $xp->query($path, $ctx)->item(0) : $xp->query($path)->item(0);

        return $node !== null ? trim((string) $node->textContent) : '';
    }
}
