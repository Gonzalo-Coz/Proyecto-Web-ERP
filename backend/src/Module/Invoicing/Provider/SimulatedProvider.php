<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Provider;

use App\Module\Invoicing\Entity\ElectronicDocument;
use App\Shared\Settings\Service\SettingsService;

/**
 * Proveedor SIMULADO para desarrollo: genera hash/QR/XML de práctica pero deja
 * el comprobante en estado PENDIENTE, porque NO hay SUNAT real que lo evalúe
 * (las boletas, además, se aceptan por resumen diario). Sin validez tributaria.
 * Se reemplaza por el adaptador PSE/OSE real configurando el servicio.
 */
final class SimulatedProvider implements ElectronicInvoiceProviderInterface
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function send(ElectronicDocument $document): ProviderResult
    {
        $hash = strtoupper(substr(hash('sha256', $document->getSeries().$document->getCorrelative().$document->getTotal()), 0, 28));

        // Contenido estándar del QR SUNAT: RUC|TIPO|SERIE|NUMERO|IGV|TOTAL|FECHA|TIPODOC|NRODOC
        $qr = implode('|', [
            $this->settings->get('company.ruc') ?? '00000000000',
            $document->getDocType(),
            $document->getSeries(),
            (string) $document->getCorrelative(),
            $document->getIgv(),
            $document->getTotal(),
            $document->getIssueDate()->format('Y-m-d'),
            $document->getCustomerDocType(),
            $document->getCustomerDocNumber(),
        ]);

        $xml = sprintf(
            "<?xml version=\"1.0\"?>\n<!-- SIMULADO: sin validez tributaria -->\n<Invoice><ID>%s-%d</ID><IssueDate>%s</IssueDate><Total>%s</Total></Invoice>",
            $document->getSeries(),
            $document->getCorrelative(),
            $document->getIssueDate()->format('Y-m-d'),
            $document->getTotal(),
        );

        return new ProviderResult(
            status: ProviderResult::PENDING,
            hash: $hash,
            qrData: $qr,
            xml: $xml,
            cdr: null, // sin CDR: no fue aceptado por SUNAT (no hay proveedor real)
            rawResponse: ['provider' => 'simulated', 'note' => 'Pendiente: sin SUNAT real', 'timestamp' => date(DATE_ATOM)],
        );
    }

    public function consult(ElectronicDocument $document): ProviderResult
    {
        // Sin SUNAT real: la consulta no aporta estado nuevo, queda PENDIENTE.
        return new ProviderResult(
            status: ProviderResult::PENDING,
            hash: null,
            qrData: null,
            xml: null,
            cdr: null,
            errorMessage: 'Proveedor simulado: no hay consulta real a SUNAT.',
            rawResponse: ['provider' => 'simulated', 'operation' => 'consult'],
        );
    }
}
