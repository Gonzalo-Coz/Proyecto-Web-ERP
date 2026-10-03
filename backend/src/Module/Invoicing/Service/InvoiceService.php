<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Service;

use App\Module\Customer\Entity\Customer;
use App\Module\Invoicing\Entity\ElectronicDocument;
use App\Module\Invoicing\Provider\ElectronicInvoiceProviderInterface;
use App\Module\Invoicing\Provider\ProviderResult;
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
                // Fecha de emisión SIEMPRE en hora de Perú: el servidor corre en UTC y,
                // de noche (hora Perú), "today" en UTC ya es mañana → SUNAT rechaza por fecha futura.
                new \DateTimeImmutable('today', new \DateTimeZone('America/Lima')),
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

    /**
     * Importa/consulta en NubeFact una nota de crédito (tipo 07) creada por fuera
     * del ERP (en el panel de NubeFact), para registrarla y poder verla aquí.
     * Se liga a la venta del comprobante original que se le indique.
     */
    public function importCreditNote(int $originalDocumentId, string $series, int $correlative): array
    {
        $original = $this->documentRepository->find($originalDocumentId)
            ?? throw new NotFoundHttpException('Comprobante original no encontrado.');

        $series = strtoupper(trim($series));
        if ($series === '' || $correlative < 1) {
            throw new UnprocessableEntityHttpException('Indica la serie y el número de la nota de crédito.');
        }

        // Si ya está registrada, solo la refresca y la devuelve.
        $existing = $this->documentRepository->findOneBy(['docType' => '07', 'series' => $series, 'correlative' => $correlative]);
        if ($existing !== null) {
            return $this->consult($existing->getId());
        }

        $nc = new ElectronicDocument(
            $original->getSale(),
            '07',
            $series,
            $correlative,
            new \DateTimeImmutable('today', new \DateTimeZone('America/Lima')),
        );

        try {
            $result = $this->provider->consult($nc);
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('No se pudo consultar la nota de crédito en NubeFact: '.$e->getMessage());
        }

        // NubeFact responde con "errors" (p. ej. "no existe") cuando no la encuentra.
        if ($result->status === ProviderResult::REJECTED
            && stripos((string) $result->errorMessage, 'no existe') !== false) {
            throw new UnprocessableEntityHttpException(sprintf(
                'La nota de crédito %s-%08d no existe en NubeFact. Verifica la serie y el número.',
                $series,
                $correlative,
            ));
        }

        // Fecha real de emisión según NubeFact (formato dd-mm-YYYY), si la devuelve.
        $rawDate = (string) ($result->rawResponse['fecha_de_emision'] ?? '');
        $parsed = $rawDate !== '' ? \DateTimeImmutable::createFromFormat('d-m-Y', $rawDate) : false;
        if ($parsed instanceof \DateTimeImmutable) {
            $nc->setIssueDate($parsed);
        }

        // Montos reales de la nota según NubeFact (no los de la venta original).
        $raw = $result->rawResponse;
        if (isset($raw['total'])) {
            $base = (float) ($raw['total_gravada'] ?? 0) + (float) ($raw['total_exonerada'] ?? 0)
                + (float) ($raw['total_inafecta'] ?? 0);
            $nc->setAmounts($base, (float) ($raw['total_igv'] ?? 0), (float) $raw['total']);
        }

        // Documento que modifica: el comprobante original elegido.
        $nc->setModifiedDocument($original);

        $nc->applyProviderResult(
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
        $this->entityManager->persist($nc);
        $this->entityManager->flush();

        $this->sunatLogger->info('Nota de crédito importada de NubeFact', [
            'number' => $nc->getFullNumber(),
            'status' => $nc->getStatus(),
            'original' => $original->getFullNumber(),
        ]);

        return $this->toArray($nc, true);
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
    public function list(int $page, int $perPage, string $search, string $status, string $docType = ''): array
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
        if ($docType !== '' && array_key_exists($docType, ElectronicDocument::TYPES)) {
            $qb->andWhere('d.docType = :dt')->setParameter('dt', $docType);
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

    /** Tipo de documento del cliente → código del catálogo 06 de SUNAT. */
    private const CLIENT_DOC_CODE = ['DNI' => '1', 'RUC' => '6', 'CE' => '4', 'PASAPORTE' => '7', 'CARNET_EXTRANJERIA' => '4'];

    /**
     * Reporte de notas de crédito en el formato del Registro de Ventas (solo las
     * columnas que usamos), incluyendo el documento que modifica cada nota.
     * Devuelve el contenido binario del .xlsx.
     */
    public function creditNotesXlsx(string $search, string $status): string
    {
        $qb = $this->documentRepository->createQueryBuilder('d')
            ->join('d.sale', 'v')->addSelect('v')
            ->where("d.docType = '07'")
            ->orderBy('d.issueDate', 'ASC')->addOrderBy('d.correlative', 'ASC');
        if ($search !== '') {
            $qb->andWhere('LOWER(d.series) LIKE :s OR LOWER(d.customerName) LIKE :s OR d.customerDocNumber LIKE :s OR LOWER(v.saleNumber) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if ($status !== '' && in_array($status, ElectronicDocument::STATUSES, true)) {
            $qb->andWhere('d.status = :st')->setParameter('st', $status);
        }
        /** @var list<ElectronicDocument> $docs */
        $docs = $qb->getQuery()->getResult();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Notas de Crédito');

        $headers = [
            'Fecha de Emisión', 'Tipo CP', 'Serie', 'Número', 'Tipo Doc.', 'Número Doc.', 'Ap. Nomb / Razón Social',
            'Dscto BI', 'Dscto IGV', 'Exonerado', 'Total CP', 'Moneda',
            'Fecha Emisión Doc Modificado', 'Tipo CP Modificado', 'Serie CP Modificado', 'Nro CP Modificado',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($docs as $d) {
            $exempt = $d->getSale()->isIgvExempt();
            $base = (float) $d->getSubtotal();
            $igv = (float) $d->getIgv();
            $total = (float) $d->getTotal();
            // La NC reduce ventas: los importes se registran en negativo.
            $dsctoBi = $exempt ? 0.0 : -$base;
            $dsctoIgv = $exempt ? 0.0 : -$igv;
            $exonerado = $exempt ? -$base : 0.0;

            $sheet->fromArray([
                $d->getIssueDate()->format('d/m/Y'),
                (int) $d->getDocType(), // 7
                $d->getSeries(),
                $d->getCorrelative(),
                self::CLIENT_DOC_CODE[$d->getCustomerDocType()] ?? '0',
                $d->getCustomerDocNumber(),
                $d->getCustomerName(),
                round($dsctoBi, 2),
                round($dsctoIgv, 2),
                round($exonerado, 2),
                round(-$total, 2),
                $d->getSale()->getCurrency() === 'USD' ? 'USD' : 'PEN',
                $d->getModifiesIssueDate()?->format('d/m/Y') ?? '',
                $d->getModifiesDocType() !== null ? (int) $d->getModifiesDocType() : '',
                $d->getModifiesSeries() ?? '',
                $d->getModifiesCorrelative() ?? '',
            ], null, 'A'.$row);
            ++$row;
        }

        // Estilo: cabecera en negrita; columnas del documento modificado resaltadas.
        $lastCol = 'P';
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E8EEF5');
        if ($row > 2) {
            $sheet->getStyle('M1:P'.($row - 1))->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFF2CC');
            $sheet->getStyle('H2:K'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'nc_').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);
        $spreadsheet->disconnectWorksheets();

        return $content;
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
            'currency' => $d->getSale()->getCurrency(),
            'status' => $d->getStatus(),
            'errorMessage' => $d->getErrorMessage(),
            // Documento que modifica (notas de crédito/débito).
            'modifiesDocType' => $d->getModifiesDocType(),
            'modifiesDocTypeName' => $d->getModifiesDocTypeName(),
            'modifiesFullNumber' => $d->getModifiesFullNumber(),
            'modifiesIssueDate' => $d->getModifiesIssueDate()?->format('Y-m-d'),
        ];

        if ($withDetail) {
            $data['hash'] = $d->getHash();
            $data['qrData'] = $d->getQrData();
            $data['cdr'] = $d->getCdr();
            $data['xml'] = $d->getXml();
            $data['pdfUrl'] = $d->getPdfUrl();
            $data['xmlUrl'] = $d->getXmlUrl();
            $data['cdrUrl'] = $d->getCdrUrl();
            // Datos de contacto del cliente: se leen de la ficha ACTUAL (no de la
            // copia guardada al emitir), así el comprobante sale completo aunque el
            // dato se haya agregado/corregido después. Aplica a DNI, RUC, CE, etc.
            $customer = $d->getSale()->getCustomer();
            $data['customerAddress'] = $customer->getAddress() ?: $d->getCustomerAddress();
            $data['customerPhone'] = $customer->getPhone() ?: $customer->getMobile();
            $data['igvRate'] = $this->settings->igvRate() * 100;
            $data['igvExempt'] = $d->getSale()->isIgvExempt();
            $data['observations'] = $d->getSale()->getNotes();
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
