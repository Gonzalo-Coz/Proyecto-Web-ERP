<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Module\Dispatch\Entity\DispatchGuide;
use App\Module\Dispatch\Service\NubefactGuideClient;
use App\Module\Invoicing\Provider\Nubefact\NubefactConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Lógica de la Guía de Remisión: numeración, estados y el payload que se envía
 * a NubeFact (generar_guia). No toca red ni base de datos.
 */
final class DispatchGuideTest extends TestCase
{
    private function makeGuide(string $mode, string $docType = 'DNI', string $docNumber = '12345678'): DispatchGuide
    {
        $guide = new DispatchGuide(
            correlative: 5,
            issueDate: new \DateTimeImmutable('2026-08-14'),
            transferDate: new \DateTimeImmutable('2026-08-15'),
            motive: '01',
            recipientDocType: $docType,
            recipientDocNumber: $docNumber,
            recipientName: 'Juan Perez',
            originAddress: 'Av. Ucayali 871',
            destinationAddress: 'Jr. Los Olivos 123',
            items: [
                ['codigo' => 'M-00001', 'descripcion' => 'Yamaha XTZ150', 'cantidad' => 1.0, 'unidad' => 'NIU'],
            ],
        );
        $guide->setOriginUbigeo('100601');
        $guide->setDestinationUbigeo('100601');
        $guide->setTransportMode($mode);
        $guide->setTotalWeight(120.5);
        $guide->setPackages(1);
        if ($mode === '02') {
            $guide->setVehiclePlate('ABC-123');
            $guide->setDriverName('Pedro Conductor');
            $guide->setDriverLicense('Q12345678');
        } else {
            $guide->setCarrierRuc('20123456789');
            $guide->setCarrierName('Transportes SAC');
        }

        return $guide;
    }

    private function client(): NubefactGuideClient
    {
        return new NubefactGuideClient(new NubefactConfig(), new NullLogger());
    }

    public function testFullNumberAndInitialStatus(): void
    {
        $guide = $this->makeGuide('02');
        self::assertSame('TTT1-00000005', $guide->getFullNumber());
        self::assertSame('PENDIENTE', $guide->getStatus());
    }

    public function testPayloadPrivateTransport(): void
    {
        $p = $this->client()->buildPayload($this->makeGuide('02'));

        self::assertSame('generar_guia', $p['operacion']);
        self::assertSame(7, $p['tipo_de_comprobante']);
        self::assertSame('TTT1', $p['serie']);
        self::assertSame(5, $p['numero']);
        self::assertSame('1', $p['cliente_tipo_de_documento']); // DNI = 1
        self::assertSame('12345678', $p['cliente_numero_de_documento']);
        self::assertSame('01', $p['motivo_de_traslado']);
        self::assertSame('02', $p['tipo_de_transporte']);
        self::assertSame(120.5, $p['peso_bruto_total']);
        self::assertSame('KGM', $p['peso_bruto_unidad_de_medida']);
        self::assertSame('100601', $p['punto_de_partida_ubigeo']);
        self::assertSame('Av. Ucayali 871', $p['punto_de_partida_direccion']);
        self::assertSame('Jr. Los Olivos 123', $p['punto_de_llegada_direccion']);
        self::assertSame('15-08-2026', $p['fecha_de_inicio_de_traslado']);
        self::assertSame('ABC-123', $p['vehiculo_placa_numero']);
        self::assertSame('Q12345678', $p['conductor_numero_licencia']);
        self::assertArrayNotHasKey('transportista_documento_numero', $p);
        self::assertCount(1, $p['items']);
        self::assertSame('Yamaha XTZ150', $p['items'][0]['descripcion']);
        self::assertSame(1.0, $p['items'][0]['cantidad']);
    }

    public function testPayloadPublicTransport(): void
    {
        $p = $this->client()->buildPayload($this->makeGuide('01'));

        self::assertSame('01', $p['tipo_de_transporte']);
        self::assertSame('6', $p['transportista_documento_tipo']);
        self::assertSame('20123456789', $p['transportista_documento_numero']);
        self::assertSame('Transportes SAC', $p['transportista_denominacion']);
        self::assertArrayNotHasKey('vehiculo_placa_numero', $p);
    }

    public function testRucRecipientMapsToCatalogCode(): void
    {
        $p = $this->client()->buildPayload($this->makeGuide('02', 'RUC', '20123456789'));
        self::assertSame('6', $p['cliente_tipo_de_documento']); // RUC = 6
        self::assertSame('20123456789', $p['cliente_numero_de_documento']);
    }

    public function testApplyProviderResultAndAnnul(): void
    {
        $guide = $this->makeGuide('02');

        $guide->applyProviderResult('ACEPTADO', 'HASH1', 'QRDATA', 'http://pdf', 'http://xml', null, ['ok' => true]);
        self::assertSame('ACEPTADO', $guide->getStatus());
        self::assertSame('http://pdf', $guide->getPdfUrl());
        self::assertSame('QRDATA', $guide->getQrData());

        $guide->markAnnulled('emitida por error');
        self::assertSame('ANULADO', $guide->getStatus());
        self::assertSame('emitida por error', $guide->getErrorMessage());
    }

    public function testInvalidProviderStatusFallsBackToPending(): void
    {
        $guide = $this->makeGuide('02');
        $guide->applyProviderResult('LO_QUE_SEA', null, null, null, null, 'x', null);
        self::assertSame('PENDIENTE', $guide->getStatus());
    }
}
