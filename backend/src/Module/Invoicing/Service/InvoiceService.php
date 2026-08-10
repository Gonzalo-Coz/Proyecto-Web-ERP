<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Service;

use App\Module\Customer\Entity\Customer;
use App\Module\Invoicing\Entity\ElectronicDocument;
use App\Module\Invoicing\Provider\ElectronicInvoiceProviderInterface;
use App\Module\Invoicing\Repository\DocumentSeriesRepository;
use App\Module\Invoicing\Repository\ElectronicDocumentRepository;
use App\Module\Sales\Entity\SaleItem;
use App\Module\Sales\Repository\SaleRepository;
use App\Shared\Settings\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElectronicDocumentRepository $documentRepository,
        private readonly DocumentSeriesRepository $seriesRepository,
        private readonly SaleRepository $saleRepository,
        private readonly ElectronicInvoiceProviderInterface $provider,
        private readonly LoggerInterface $sunatLogger,
        private readonly SettingsService $settings,
    ) {
    }

    /** Datos de la empresa emisora (Configuración) para la impresión. */
    private function companyData(): array
    {
        return [
            'name' => $this->settings->get('company.name') ?? '',
            'tradeName' => $this->settings->get('company.trade_name') ?? '',
            'ruc' => $this->settings->get('company.ruc') ?? '',
            'address' => $this->settings->get('company.address') ?? '',
            'department' => $this->settings->get('company.department') ?? '',
            'province' => $this->settings->get('company.province') ?? '',
            'district' => $this->settings->get('company.district') ?? '',
            'phone' => $this->settings->get('company.phone') ?? '',
            'email' => $this->settings->get('company.email') ?? '',
            // Logo subido en el Perfil; si no hay, usa el logo estático de la tienda.
            'logo' => $this->settings->get('company.logo_full_path') ?: '/brand/logo-full.png',
            'banks' => $this->bankAccounts(),
        ];
    }

    /** @return list<array{name: string, account: string, cci: string}> */
    private function bankAccounts(): array
    {
        $banks = [];
        foreach (['bank1', 'bank2'] as $b) {
            $name = trim((string) $this->settings->get("company.{$b}_name"));
            if ($name === '') {
                continue;
            }
            $banks[] = [
                'name' => $name,
                'account' => trim((string) $this->settings->get("company.{$b}_account")),
                'cci' => trim((string) $this->settings->get("company.{$b}_cci")),
            ];
        }

        return $banks;
    }

    /** XML del comprobante para descarga (nombre estándar SUNAT: RUC-TIPO-SERIE-CORRELATIVO.xml). */
    public function xmlForDownload(int $id): array
    {
        $d = $this->documentRepository->find($id)
            ?? throw new NotFoundHttpException('Comprobante no encontrado.');
        $ruc = $this->settings->get('company.ruc') ?? '00000000000';
        $filename = sprintf('%s-%s-%s-%08d.xml', $ruc, $d->getDocType(), $d->getSeries(), $d->getCorrelative());

        return ['filename' => $filename, 'xml' => $d->getXml() ?? ''];
    }

    /**
     * Emite Boleta (03) o Factura (01) para una venta COMPLETADA.
     * La asignación de serie/correlativo es transaccional con bloqueo (§23.15).
     * El envío al proveedor ocurre después: si falla, el comprobante queda
     * PENDIENTE/RECHAZADO y puede reenviarse sin perder el correlativo.
     */
    public function issueForSale(int $saleId, string $docType): array
    {
        if (!in_array($docType, ['01', '03'], true)) {
            throw new UnprocessableEntityHttpException('Tipo de comprobante inválido (01=Factura, 03=Boleta).');
        }

        $sale = $this->saleRepository->find($saleId)
            ?? throw new NotFoundHttpException('Venta no encontrada.');

        if ($sale->getStatus() !== 'COMPLETADA') {
            throw new ConflictHttpException('Solo las ventas completadas generan comprobante.');
        }
        if ($this->documentRepository->findActiveForSale($sale) !== null) {
            throw new ConflictHttpException('La venta ya tiene un comprobante emitido.');
        }
        // Regla §19: no emitir sin cliente válido. Factura exige RUC.
        if ($docType === '01' && $sale->getCustomer()->getDocumentType() !== 'RUC') {
            throw new UnprocessableEntityHttpException('La Factura requiere un cliente con RUC; usa Boleta para personas naturales.');
        }
        // Boleta simple (Público General, sin datos): SUNAT solo la permite sin
        // identificar al cliente hasta S/ 700. Por encima, exige el DNI.
        if ($docType === '03'
            && $sale->getCustomer()->getDocumentNumber() === Customer::GENERIC_DOC_NUMBER
            && (float) $sale->getTotal() > 700.0
        ) {
            throw new UnprocessableEntityHttpException('La boleta a "Público General" solo puede emitirse hasta S/ 700. Para montos mayores, registra al cliente con su DNI.');
        }

        $document = $this->entityManager->wrapInTransaction(function () use ($sale, $docType): ElectronicDocument {
            $series = $this->seriesRepository->lockActiveSeries($docType);
            $document = new ElectronicDocument(
                $sale,
                $docType,
                $series->getSeries(),
                $series->nextCorrelative(),
                new \DateTimeImmutable('today'),
            );
            $this->entityManager->persist($document);
            $this->entityManager->flush();

            return $document;
        });

        return $this->sendToProvider($document);
    }

    /**
     * Marca el comprobante como ANULADO en el ERP, reflejando la baja hecha en
     * NubeFact/SUNAT. Deja de estar "activo", de modo que su venta pueda anularse
     * (lo que devuelve la moto/stock). No emite nada al proveedor.
     */
    public function annul(int $id, string $reason): array
    {
        $document = $this->documentRepository->find($id)
            ?? throw new NotFoundHttpException('Comprobante no encontrado.');

        if ($document->getStatus() === 'ANULADO') {
            throw new ConflictHttpException('El comprobante ya está anulado.');
        }

        $document->markAnnulled($reason);
        $this->entityManager->flush();

        $this->sunatLogger->info('Comprobante anulado (baja reflejada en el ERP)', [
            'number' => $document->getFullNumber(),
            'reason' => $reason,
        ]);

        return $this->toArray($document, true);
    }

    /** Reenvía a SUNAT un comprobante PENDIENTE o RECHAZADO (§15). */
    public function resend(int $id): array
    {
        $document = $this->documentRepository->find($id)
            ?? throw new NotFoundHttpException('Comprobante no encontrado.');

        if ($document->getStatus() === 'ACEPTADO') {
            throw new ConflictHttpException('El comprobante ya fue aceptado por SUNAT; no se reenvía.');
        }

        return $this->sendToProvider($document);
    }

    /**
     * Consulta el estado real del comprobante en el proveedor y lo sincroniza.
     * Útil cuando quedó desincronizado (registrado en el proveedor pero PENDIENTE
     * o RECHAZADO por "ya existe" de nuestro lado): recupera estado + enlaces.
     */
    public function consult(int $id): array
    {
        $document = $this->documentRepository->find($id)
            ?? throw new NotFoundHttpException('Comprobante no encontrado.');

        try {
            $result = $this->provider->consult($document);
        } catch (\Throwable $e) {
            $this->sunatLogger->error('Fallo al consultar el comprobante en el proveedor', [
                'number' => $document->getFullNumber(),
                'error' => $e->getMessage(),
            ]);
            throw new UnprocessableEntityHttpException('No se pudo consultar el comprobante: '.$e->getMessage());
        }

        $document->applyProviderResult(
            $result->status,
            $result->hash,
            $result->qrData,
            $result->xml,
            $result->cdr,
            $result->errorMessage,
            $result->rawResponse,
            $result->pdfUrl,
            $result->xmlUrl,
            $result->cdrUrl,
        );
        $this->entityManager->flush();

        $this->sunatLogger->info('Comprobante consultado', [
            'number' => $document->getFullNumber(),
            'status' => $document->getStatus(),
        ]);

        return $this->toArray($document, true);
    }

    private function sendToProvider(ElectronicDocument $document): array
    {
        try {
            $result = $this->provider->send($document);
            $document->applyProviderResult(
                $result->status,
                $result->hash,
                $result->qrData,
                $result->xml,
                $result->cdr,
                $result->errorMessage,
                $result->rawResponse,
                $result->pdfUrl,
                $result->xmlUrl,
                $result->cdrUrl,
            );
            $this->sunatLogger->info('Comprobante enviado', [
                'number' => $document->getFullNumber(),
                'status' => $document->getStatus(),
            ]);
        } catch (\Throwable $e) {
            // El comprobante conserva su correlativo y queda PENDIENTE para reenvío
            $this->sunatLogger->error('Fallo de comunicación con el proveedor SUNAT', [
                'number' => $document->getFullNumber(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->entityManager->flush();

        return $this->toArray($document, true);
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $status): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $qb = $this->documentRepository->createQueryBuilder('d')
            ->join('d.sale', 'v')->addSelect('v')
            ->orderBy('d.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(d.series) LIKE :s OR LOWER(d.customerName) LIKE :s OR d.customerDocNumber LIKE :s OR LOWER(v.saleNumber) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if ($status !== '' && in_array($status, ElectronicDocument::STATUSES, true)) {
            $qb->andWhere('d.status = :st')->setParameter('st', $status);
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map(fn (ElectronicDocument $d) => $this->toArray($d, false), iterator_to_array($paginator, false)),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ];
    }

    public function get(int $id): array
    {
        $document = $this->documentRepository->find($id)
            ?? throw new NotFoundHttpException('Comprobante no encontrado.');

        return $this->toArray($document, true);
    }

    public function toArray(ElectronicDocument $d, bool $withDetail): array
    {
        $data = [
            'id' => $d->getId(),
            'saleId' => $d->getSale()->getId(),
            'saleNumber' => $d->getSale()->getSaleNumber(),
            'docType' => $d->getDocType(),
            'docTypeName' => $d->getDocTypeName(),
            'fullNumber' => $d->getFullNumber(),
            'issueDate' => $d->getIssueDate()->format('Y-m-d'),
            'customerName' => $d->getCustomerName(),
            'customerDocument' => $d->getCustomerDocType().' '.$d->getCustomerDocNumber(),
            'discountTotal' => $d->getDiscountTotal(),
            'subtotal' => $d->getSubtotal(),
            'igv' => $d->getIgv(),
            'total' => $d->getTotal(),
            'status' => $d->getStatus(),
            'errorMessage' => $d->getErrorMessage(),
        ];

        if ($withDetail) {
            $data['hash'] = $d->getHash();
            $data['qrData'] = $d->getQrData();
            $data['cdr'] = $d->getCdr();
            $data['xml'] = $d->getXml();
            $data['pdfUrl'] = $d->getPdfUrl();
            $data['xmlUrl'] = $d->getXmlUrl();
            $data['cdrUrl'] = $d->getCdrUrl();
            $data['customerAddress'] = $d->getCustomerAddress();
            $data['igvRate'] = $this->settings->igvRate() * 100;
            $data['company'] = $this->companyData();
            $data['items'] = array_map(static fn (SaleItem $i): array => [
                'code' => $i->getSparePart()?->getInternalCode() ?? $i->getMotorcycleUnit()?->getInternalCode() ?? '',
                'description' => $i->getDescription(),
                'quantity' => $i->getQuantity(),
                'unitPrice' => $i->getUnitPrice(),
                'discount' => $i->getDiscount(),
                'lineTotal' => $i->getLineTotal(),
            ], $d->getSale()->getItems()->toArray());
        }

        return $data;
    }
}
