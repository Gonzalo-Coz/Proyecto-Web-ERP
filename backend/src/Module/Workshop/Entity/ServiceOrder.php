<?php

declare(strict_types=1);

namespace App\Module\Workshop\Entity;

use App\Module\Customer\Entity\Customer;
use App\Module\Motorcycle\Entity\MotorcycleUnit;
use App\Module\Workshop\Repository\ServiceOrderRepository;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Orden de servicio del taller (§14). Si la motocicleta fue vendida por la
 * empresa se vincula a su unidad (expediente digital); si es externa, se
 * describe libremente (placa + descripción).
 */
#[ORM\Entity(repositoryClass: ServiceOrderRepository::class)]
#[ORM\Table(name: 'service_orders')]
#[ORM\UniqueConstraint(name: 'uq_service_order_number', columns: ['order_number'])]
#[ORM\Index(columns: ['status'], name: 'idx_service_order_status')]
#[ORM\HasLifecycleCallbacks]
class ServiceOrder
{
    public const STATUSES = [
        'RECIBIDA', 'EN_DIAGNOSTICO', 'ESPERANDO_REPUESTOS',
        'EN_REPARACION', 'LISTA_PARA_ENTREGA', 'ENTREGADA', 'GARANTIA', 'ANULADA',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $orderNumber = '';

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    /** Unidad vendida por la empresa (expediente digital), si aplica. */
    #[ORM\ManyToOne(targetEntity: MotorcycleUnit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?MotorcycleUnit $motorcycleUnit = null;

    /** Motocicleta externa: descripción libre (obligatoria si no hay unidad). */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $motorcycleDescription = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $plate = null;

    /** Quién ingresa la moto / a nombre de otra persona (opcional). */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $broughtBy = null;

    /** Plan de mantenimiento aplicado (modelo y kilometraje), si se cargó uno. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $planModel = null;

    #[ORM\Column(nullable: true)]
    private ?int $planKm = null;

    #[ORM\Column(nullable: true)]
    private ?int $mileage = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $entryDate;

    /** Hora de entrada de la moto (HH:MM). */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $entryTime = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $estimatedDate = null;

    /** Tiempo estimado de trabajo, en horas (reemplaza a la fecha estimada). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $estimatedHours = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mechanicName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $diagnosis = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20, options: ['default' => 'RECIBIDA'])]
    private string $status = 'RECIBIDA';

    /** ID de la venta generada al facturar la orden (módulo Ventas). */
    #[ORM\Column(nullable: true)]
    private ?int $invoiceSaleId = null;

    /** @var Collection<int, ServiceOrderItem> */
    #[ORM\OneToMany(targetEntity: ServiceOrderItem::class, mappedBy: 'serviceOrder', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(Customer $customer, \DateTimeImmutable $entryDate)
    {
        $this->customer = $customer;
        $this->entryDate = $entryDate;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function assignNumber(int $sequence): void
    {
        $this->orderNumber = sprintf('OS-%06d', $sequence);
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getMotorcycleUnit(): ?MotorcycleUnit
    {
        return $this->motorcycleUnit;
    }

    public function setMotorcycleUnit(?MotorcycleUnit $v): void
    {
        $this->motorcycleUnit = $v;
    }

    public function getMotorcycleDescription(): ?string
    {
        return $this->motorcycleDescription;
    }

    public function setMotorcycleDescription(?string $v): void
    {
        $this->motorcycleDescription = $v;
    }

    public function getPlate(): ?string
    {
        return $this->plate;
    }

    public function setPlate(?string $v): void
    {
        $this->plate = $v !== null ? strtoupper($v) : null;
    }

    public function getBroughtBy(): ?string
    {
        return $this->broughtBy;
    }

    public function setBroughtBy(?string $v): void
    {
        $this->broughtBy = $v !== null && trim($v) !== '' ? trim($v) : null;
    }

    public function getPlanModel(): ?string
    {
        return $this->planModel;
    }

    public function getPlanKm(): ?int
    {
        return $this->planKm;
    }

    public function setPlan(?string $model, ?int $km): void
    {
        $this->planModel = $model;
        $this->planKm = $km;
    }

    public function getMileage(): ?int
    {
        return $this->mileage;
    }

    public function setMileage(?int $v): void
    {
        $this->mileage = $v;
    }

    public function getEntryDate(): \DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function getEstimatedDate(): ?\DateTimeImmutable
    {
        return $this->estimatedDate;
    }

    public function setEstimatedDate(?\DateTimeImmutable $v): void
    {
        $this->estimatedDate = $v;
    }

    public function getEntryTime(): ?string
    {
        return $this->entryTime;
    }

    public function setEntryTime(?string $v): void
    {
        $v = $v !== null ? trim($v) : null;
        $this->entryTime = $v !== null && preg_match('/^\d{1,2}:\d{2}$/', $v) === 1 ? $v : null;
    }

    public function getEstimatedHours(): ?string
    {
        return $this->estimatedHours;
    }

    public function setEstimatedHours(?float $v): void
    {
        $this->estimatedHours = $v !== null && $v > 0 ? number_format($v, 2, '.', '') : null;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getMechanicName(): ?string
    {
        return $this->mechanicName;
    }

    public function setMechanicName(?string $v): void
    {
        $this->mechanicName = $v;
    }

    public function getDiagnosis(): ?string
    {
        return $this->diagnosis;
    }

    public function setDiagnosis(?string $v): void
    {
        $this->diagnosis = $v;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $v): void
    {
        $this->notes = $v;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isDelivered(): bool
    {
        return $this->status === 'ENTREGADA';
    }

    public function markDelivered(): void
    {
        $this->status = 'ENTREGADA';
        $this->deliveredAt = new \DateTimeImmutable();
    }

    public function getInvoiceSaleId(): ?int
    {
        return $this->invoiceSaleId;
    }

    public function setInvoiceSaleId(?int $v): void
    {
        $this->invoiceSaleId = $v;
    }

    /** @return Collection<int, ServiceOrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ServiceOrderItem $item): void
    {
        $this->items->add($item);
    }

    public function removeItem(ServiceOrderItem $item): void
    {
        $this->items->removeElement($item);
    }

    public function getTotal(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += (float) $item->getLineTotal();
        }

        return $total;
    }
}
