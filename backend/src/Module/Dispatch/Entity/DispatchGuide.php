<?php

declare(strict_types=1);

namespace App\Module\Dispatch\Entity;

use App\Module\Dispatch\Repository\DispatchGuideRepository;
use App\Module\Sales\Entity\Sale;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Guía de Remisión Electrónica (GRE Remitente, tipo 09). Documenta el TRASLADO
 * de bienes (no una venta). Módulo independiente: no altera Ventas ni Facturación.
 *
 * Se emite a SUNAT vía NubeFact (operación generar_guia). Los ítems trasladados
 * se guardan como JSON (código, descripción, cantidad, unidad) para no acoplar el
 * módulo al catálogo de productos.
 */
#[ORM\Entity(repositoryClass: DispatchGuideRepository::class)]
#[ORM\Table(name: 'dispatch_guides')]
#[ORM\UniqueConstraint(name: 'uq_dispatch_number', columns: ['series', 'correlative'])]
#[ORM\Index(columns: ['status'], name: 'idx_dispatch_status')]
#[ORM\HasLifecycleCallbacks]
class DispatchGuide
{
    use TimestampableTrait;

    /** Motivos de traslado (catálogo 20 SUNAT, los más usados por un dealer). */
    public const MOTIVOS = [
        '01' => 'Venta',
        '02' => 'Compra',
        '04' => 'Traslado entre establecimientos de la misma empresa',
        '08' => 'Importación',
        '13' => 'Otros',
    ];

    /** Modalidad de transporte (catálogo 18). */
    public const MODALIDADES = ['01' => 'Transporte público', '02' => 'Transporte privado'];

    /** @return list<string> códigos de motivo válidos (para validación). */
    public static function motiveCodes(): array
    {
        return array_keys(self::MOTIVOS);
    }

    public const STATUSES = ['PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'ANULADO'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 4)]
    private string $series = 'TTT1';

    #[ORM\Column]
    private int $correlative;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $issueDate;

    /** Fecha de inicio del traslado. */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $transferDate;

    /** Motivo de traslado (código catálogo 20). */
    #[ORM\Column(length: 2)]
    private string $motive = '01';

    // --- Destinatario ---
    #[ORM\Column(length: 15)]
    private string $recipientDocType = 'DNI';

    #[ORM\Column(length: 20)]
    private string $recipientDocNumber;

    #[ORM\Column(length: 200)]
    private string $recipientName;

    // --- Punto de partida y de llegada ---
    #[ORM\Column(length: 200)]
    private string $originAddress;

    #[ORM\Column(length: 6, nullable: true)]
    private ?string $originUbigeo = null;

    #[ORM\Column(length: 200)]
    private string $destinationAddress;

    #[ORM\Column(length: 6, nullable: true)]
    private ?string $destinationUbigeo = null;

    // --- Transporte ---
    #[ORM\Column(length: 2)]
    private string $transportMode = '02';

    /** Transporte público: RUC y razón social del transportista. */
    #[ORM\Column(length: 11, nullable: true)]
    private ?string $carrierRuc = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $carrierName = null;

    /** Transporte privado: placa del vehículo y licencia del conductor. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $vehiclePlate = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $driverLicense = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $driverName = null;

    /** Peso bruto total y unidad (KGM). */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $totalWeight = '0.000';

    #[ORM\Column(length: 3)]
    private string $weightUnit = 'KGM';

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $packages = 1;

    /** Ítems trasladados: [{codigo, descripcion, cantidad, unidad}]. */
    #[ORM\Column(type: 'json')]
    private array $items = [];

    /** Venta que originó la guía (opcional: GRE por venta de moto). */
    #[ORM\ManyToOne(targetEntity: Sale::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sale $sale = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    // --- Resultado SUNAT/NubeFact ---
    #[ORM\Column(length: 10, options: ['default' => 'PENDIENTE'])]
    private string $status = 'PENDIENTE';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $hash = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $qrData = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $pdfUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $xmlUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $providerResponse = null;

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        int $correlative,
        \DateTimeImmutable $issueDate,
        \DateTimeImmutable $transferDate,
        string $motive,
        string $recipientDocType,
        string $recipientDocNumber,
        string $recipientName,
        string $originAddress,
        string $destinationAddress,
        array $items,
    ) {
        $this->correlative = $correlative;
        $this->issueDate = $issueDate;
        $this->transferDate = $transferDate;
        $this->motive = $motive;
        $this->recipientDocType = $recipientDocType;
        $this->recipientDocNumber = $recipientDocNumber;
        $this->recipientName = $recipientName;
        $this->originAddress = $originAddress;
        $this->destinationAddress = $destinationAddress;
        $this->items = $items;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullNumber(): string
    {
        return sprintf('%s-%08d', $this->series, $this->correlative);
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getCorrelative(): int
    {
        return $this->correlative;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function getTransferDate(): \DateTimeImmutable
    {
        return $this->transferDate;
    }

    public function getMotive(): string
    {
        return $this->motive;
    }

    public function getRecipientDocType(): string
    {
        return $this->recipientDocType;
    }

    public function getRecipientDocNumber(): string
    {
        return $this->recipientDocNumber;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function getOriginAddress(): string
    {
        return $this->originAddress;
    }

    public function getOriginUbigeo(): ?string
    {
        return $this->originUbigeo;
    }

    public function setOriginUbigeo(?string $v): void
    {
        $this->originUbigeo = $v;
    }

    public function getDestinationAddress(): string
    {
        return $this->destinationAddress;
    }

    public function getDestinationUbigeo(): ?string
    {
        return $this->destinationUbigeo;
    }

    public function setDestinationUbigeo(?string $v): void
    {
        $this->destinationUbigeo = $v;
    }

    public function getTransportMode(): string
    {
        return $this->transportMode;
    }

    public function setTransportMode(string $v): void
    {
        $this->transportMode = $v;
    }

    public function getCarrierRuc(): ?string
    {
        return $this->carrierRuc;
    }

    public function setCarrierRuc(?string $v): void
    {
        $this->carrierRuc = $v;
    }

    public function getCarrierName(): ?string
    {
        return $this->carrierName;
    }

    public function setCarrierName(?string $v): void
    {
        $this->carrierName = $v;
    }

    public function getVehiclePlate(): ?string
    {
        return $this->vehiclePlate;
    }

    public function setVehiclePlate(?string $v): void
    {
        $this->vehiclePlate = $v;
    }

    public function getDriverLicense(): ?string
    {
        return $this->driverLicense;
    }

    public function setDriverLicense(?string $v): void
    {
        $this->driverLicense = $v;
    }

    public function getDriverName(): ?string
    {
        return $this->driverName;
    }

    public function setDriverName(?string $v): void
    {
        $this->driverName = $v;
    }

    public function getTotalWeight(): string
    {
        return $this->totalWeight;
    }

    public function setTotalWeight(float $v): void
    {
        $this->totalWeight = number_format($v, 3, '.', '');
    }

    public function getWeightUnit(): string
    {
        return $this->weightUnit;
    }

    public function getPackages(): int
    {
        return $this->packages;
    }

    public function setPackages(int $v): void
    {
        $this->packages = max(1, $v);
    }

    /** @return array<int, array<string, mixed>> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(?Sale $sale): void
    {
        $this->sale = $sale;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $v): void
    {
        $this->observations = $v;
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

    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    public function getXmlUrl(): ?string
    {
        return $this->xmlUrl;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /** Aplica el resultado devuelto por el proveedor (NubeFact). */
    public function applyProviderResult(
        string $status,
        ?string $hash,
        ?string $qrData,
        ?string $pdfUrl,
        ?string $xmlUrl,
        ?string $errorMessage,
        ?array $rawResponse,
    ): void {
        $this->status = in_array($status, self::STATUSES, true) ? $status : 'PENDIENTE';
        $this->hash = $hash;
        $this->qrData = $qrData;
        $this->pdfUrl = $pdfUrl;
        $this->xmlUrl = $xmlUrl;
        $this->errorMessage = $errorMessage;
        $this->providerResponse = $rawResponse;
    }

    public function markAnnulled(?string $reason): void
    {
        $this->status = 'ANULADO';
        $this->errorMessage = $reason !== null && trim($reason) !== '' ? trim($reason) : 'Anulada';
    }
}
