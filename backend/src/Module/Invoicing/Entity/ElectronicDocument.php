<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Entity;

use App\Module\Invoicing\Repository\ElectronicDocumentRepository;
use App\Module\Sales\Entity\Sale;
use Doctrine\ORM\Mapping as ORM;

/**
 * Comprobante electrónico (§15). Los datos tributarios son INMUTABLES una
 * vez enviados a SUNAT (§19): no existen setters para ellos; solo el
 * resultado del envío se actualiza (applyProviderResult).
 */
#[ORM\Entity(repositoryClass: ElectronicDocumentRepository::class)]
#[ORM\Table(name: 'electronic_documents')]
#[ORM\UniqueConstraint(name: 'uq_document_number', columns: ['doc_type', 'series', 'correlative'])]
#[ORM\Index(columns: ['status'], name: 'idx_document_status')]
#[ORM\Index(columns: ['issue_date'], name: 'idx_document_issue_date')]
class ElectronicDocument
{
    /** Códigos SUNAT de tipo de comprobante. */
    public const TYPES = ['01' => 'FACTURA', '03' => 'BOLETA', '07' => 'NOTA DE CRÉDITO', '08' => 'NOTA DE DÉBITO'];
    public const STATUSES = ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sale::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Sale $sale;

    #[ORM\Column(length: 2)]
    private string $docType;

    #[ORM\Column(length: 4)]
    private string $series;

    #[ORM\Column]
    private int $correlative;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $issueDate;

    // --- Snapshot del cliente (§15: el comprobante es histórico estable) ---

    #[ORM\Column(length: 200)]
    private string $customerName;

    #[ORM\Column(length: 15)]
    private string $customerDocType;

    #[ORM\Column(length: 20)]
    private string $customerDocNumber;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $customerAddress = null;

    /** Descuento total aplicado (Adición A1: visible en el comprobante). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $discountTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $subtotal;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $igv;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total;

    // --- Resultado SUNAT (no editable manualmente, §15) ---

    #[ORM\Column(length: 10, options: ['default' => 'PENDIENTE'])]
    private string $status = 'PENDIENTE';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $hash = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $qrData = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $xml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cdr = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $providerResponse = null;

    // --- Enlaces que hospeda el proveedor (NubeFact devuelve URLs; §Almacenamiento: solo rutas) ---

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $pdfUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $xmlUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $cdrUrl = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Sale $sale,
        string $docType,
        string $series,
        int $correlative,
        \DateTimeImmutable $issueDate,
    ) {
        $this->sale = $sale;
        $this->docType = $docType;
        $this->series = $series;
        $this->correlative = $correlative;
        $this->issueDate = $issueDate;
        $this->customerName = $sale->getCustomer()->getName();
        $this->customerDocType = $sale->getCustomer()->getDocumentType();
        $this->customerDocNumber = $sale->getCustomer()->getDocumentNumber();
        $this->customerAddress = $sale->getCustomer()->getAddress();
        $this->discountTotal = $sale->getTotalDiscount();
        $this->subtotal = $sale->getSubtotal();
        $this->igv = $sale->getIgv();
        $this->total = $sale->getTotal();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): Sale
    {
        return $this->sale;
    }

    public function getDocType(): string
    {
        return $this->docType;
    }

    public function getDocTypeName(): string
    {
        return self::TYPES[$this->docType] ?? $this->docType;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getCorrelative(): int
    {
        return $this->correlative;
    }

    public function getFullNumber(): string
    {
        return sprintf('%s-%08d', $this->series, $this->correlative);
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCustomerDocType(): string
    {
        return $this->customerDocType;
    }

    public function getCustomerDocNumber(): string
    {
        return $this->customerDocNumber;
    }

    public function getCustomerAddress(): ?string
    {
        return $this->customerAddress;
    }

    public function getDiscountTotal(): string
    {
        return $this->discountTotal;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function getIgv(): string
    {
        return $this->igv;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function getQrData(): ?string
    {
        return $this->qrData;
    }

    public function getXml(): ?string
    {
        return $this->xml;
    }

    public function getCdr(): ?string
    {
        return $this->cdr;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    public function getXmlUrl(): ?string
    {
        return $this->xmlUrl;
    }

    public function getCdrUrl(): ?string
    {
        return $this->cdrUrl;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Único punto de mutación: aplicar la respuesta del proveedor. */
    public function applyProviderResult(
        string $status,
        ?string $hash,
        ?string $qrData,
        ?string $xml,
        ?string $cdr,
        ?string $errorMessage,
        array $rawResponse,
        ?string $pdfUrl = null,
        ?string $xmlUrl = null,
        ?string $cdrUrl = null,
    ): void {
        $this->status = $status;
        $this->hash = $hash;
        $this->qrData = $qrData;
        $this->xml = $xml;
        $this->cdr = $cdr;
        $this->errorMessage = $errorMessage;
        $this->providerResponse = $rawResponse;
        $this->pdfUrl = $pdfUrl;
        $this->xmlUrl = $xmlUrl;
        $this->cdrUrl = $cdrUrl;
    }
}
