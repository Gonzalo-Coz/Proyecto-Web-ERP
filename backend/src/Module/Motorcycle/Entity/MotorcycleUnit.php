<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Entity;

use App\Module\Motorcycle\Repository\MotorcycleUnitRepository;
use App\Module\Supplier\Entity\Supplier;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unidad física (§9.2): cada motocicleta es única, identificada por VIN.
 * El VIN tiene unicidad TOTAL (incluye registros eliminados): la
 * trazabilidad del vehículo no permite reutilizarlo jamás.
 */
#[ORM\Entity(repositoryClass: MotorcycleUnitRepository::class)]
#[ORM\Table(name: 'motorcycle_units')]
#[ORM\UniqueConstraint(name: 'uq_unit_vin', columns: ['vin'])]
#[ORM\UniqueConstraint(name: 'uq_unit_engine_number', columns: ['engine_number'])]
#[ORM\UniqueConstraint(name: 'uq_unit_internal_code', columns: ['internal_code'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\Index(columns: ['status'], name: 'idx_unit_status')]
#[ORM\HasLifecycleCallbacks]
class MotorcycleUnit implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    public const STATUSES = ['DISPONIBLE', 'RESERVADA', 'VENDIDA', 'EN_TALLER', 'GARANTIA', 'BAJA'];

    /** Estados que solo el sistema asigna (venta, taller); no editables a mano. */
    public const SYSTEM_STATUSES = ['VENDIDA', 'EN_TALLER', 'GARANTIA'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $internalCode;

    #[ORM\Column(length: 17)]
    private string $vin;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $engineNumber = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $chassisNumber = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $series = null;

    #[ORM\ManyToOne(targetEntity: MotorcycleModel::class)]
    #[ORM\JoinColumn(nullable: false)]
    private MotorcycleModel $model;

    #[ORM\Column(nullable: true)]
    private ?int $manufactureYear = null;

    #[ORM\Column(length: 50)]
    private string $color;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $entryDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $purchaseDate = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Supplier $supplier = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $salePrice = null;

    #[ORM\Column(length: 20)]
    private string $status = 'DISPONIBLE';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    // Datos de importación (obligatorios en el comprobante de venta de vehículos importados).
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $duaNumber = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $duaItem = null;

    public function __construct(string $internalCode, string $vin, MotorcycleModel $model, string $color)
    {
        $this->internalCode = strtoupper($internalCode);
        $this->vin = strtoupper($vin);
        $this->model = $model;
        $this->color = $color;
        $this->entryDate = new \DateTimeImmutable('today');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInternalCode(): string
    {
        return $this->internalCode;
    }

    public function setInternalCode(string $value): void
    {
        $this->internalCode = strtoupper($value);
    }

    public function getVin(): string
    {
        return $this->vin;
    }

    public function getEngineNumber(): ?string
    {
        return $this->engineNumber;
    }

    public function setEngineNumber(?string $value): void
    {
        $this->engineNumber = $value !== null ? strtoupper($value) : null;
    }

    public function getChassisNumber(): ?string
    {
        return $this->chassisNumber;
    }

    public function setChassisNumber(?string $value): void
    {
        $this->chassisNumber = $value;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $value): void
    {
        $this->series = $value;
    }

    public function getModel(): MotorcycleModel
    {
        return $this->model;
    }

    public function setModel(MotorcycleModel $model): void
    {
        $this->model = $model;
    }

    public function getManufactureYear(): ?int
    {
        return $this->manufactureYear;
    }

    public function setManufactureYear(?int $value): void
    {
        $this->manufactureYear = $value;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $value): void
    {
        $this->color = $value;
    }

    public function getEntryDate(): \DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function setEntryDate(\DateTimeImmutable $value): void
    {
        $this->entryDate = $value;
    }

    public function getPurchaseDate(): ?\DateTimeImmutable
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(?\DateTimeImmutable $value): void
    {
        $this->purchaseDate = $value;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $value): void
    {
        $this->supplier = $value;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(?string $value): void
    {
        $this->purchasePrice = $value;
    }

    public function getSalePrice(): ?string
    {
        return $this->salePrice;
    }

    public function setSalePrice(?string $value): void
    {
        $this->salePrice = $value;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $value): void
    {
        $this->location = $value;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $value): void
    {
        $this->notes = $value;
    }

    public function getDuaNumber(): ?string
    {
        return $this->duaNumber;
    }

    public function setDuaNumber(?string $value): void
    {
        $this->duaNumber = $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    public function getDuaItem(): ?string
    {
        return $this->duaItem;
    }

    public function setDuaItem(?string $value): void
    {
        $this->duaItem = $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    public function isSold(): bool
    {
        return $this->status === 'VENDIDA';
    }
}
