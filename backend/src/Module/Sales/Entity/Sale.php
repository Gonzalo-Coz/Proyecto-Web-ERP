<?php

declare(strict_types=1);

namespace App\Module\Sales\Entity;

use App\Module\Customer\Entity\Customer;
use App\Module\Sales\Repository\SaleRepository;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Venta (§12): un mismo documento evoluciona por el flujo comercial
 * COTIZACION → RESERVA (opcional) → COMPLETADA, o se anula.
 * Con cuentas por cobrar: pagos parciales y saldo (decisión aprobada).
 */
#[ORM\Entity(repositoryClass: SaleRepository::class)]
#[ORM\Table(name: 'sales')]
#[ORM\UniqueConstraint(name: 'uq_sale_number', columns: ['sale_number'])]
#[ORM\Index(columns: ['status'], name: 'idx_sale_status')]
#[ORM\Index(columns: ['sale_date'], name: 'idx_sale_date')]
#[ORM\HasLifecycleCallbacks]
class Sale
{
    public const STATUSES = ['COTIZACION', 'RESERVA', 'COMPLETADA', 'ANULADA'];
    public const PAYMENT_STATUSES = ['PENDIENTE', 'PARCIAL', 'PAGADO'];
    public const IGV_RATE = 0.18;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $saleNumber = '';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $saleDate;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    /** Vendedor (§6: ventas por vendedor). */
    #[ORM\Column(length: 100)]
    private string $seller;

    #[ORM\Column(length: 15, options: ['default' => 'COTIZACION'])]
    private string $status = 'COTIZACION';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $subtotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $igv = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total = '0.00';

    /**
     * IGV incluido en el precio (true = zona local/Tingo María: el precio ya
     * contiene el IGV) o agregado encima (false = venta al exterior/fuera de zona).
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $igvIncluded = true;

    /**
     * Operación exonerada de IGV (Ley de Amazonía, Ley 27037): no se aplica IGV.
     * Si es true, prevalece sobre igvIncluded: IGV = 0 y la base va como exonerada.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $igvExempt = false;

    /** Moneda de la venta: PEN (soles) o USD (dólares). No se convierte; se guarda tal cual. */
    #[ORM\Column(length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    /** Canal/categoría de la venta: MOSTRADOR (por defecto) o TALLER (generada desde una orden de servicio). */
    #[ORM\Column(length: 12, options: ['default' => 'MOSTRADOR'])]
    private string $channel = 'MOSTRADOR';

    /** Datos para el reporte Yamaha de venta de motos (se llenan al vender una moto). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $retailPaymentType = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $retailFinancialEntity = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $retailTcea = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $retailBonusYmdp = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $retailBonusDealer = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $retailCampaign = null;

    /** Descuento global sobre el total del comprobante (Adición A1 / 24.1). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $globalDiscount = '0.00';

    /** Autorización de descuento sobre el límite del vendedor (Adición A2 / 24.2). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discountAuthorizedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $discountAuthorizedAt = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $paidAmount = '0.00';

    #[ORM\Column(length: 10, options: ['default' => 'PENDIENTE'])]
    private string $paymentStatus = 'PENDIENTE';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $reservationExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, SaleItem> */
    #[ORM\OneToMany(targetEntity: SaleItem::class, mappedBy: 'sale', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    /** @var Collection<int, SalePayment> */
    #[ORM\OneToMany(targetEntity: SalePayment::class, mappedBy: 'sale')]
    private Collection $payments;

    public function __construct(Customer $customer, string $seller, \DateTimeImmutable $saleDate)
    {
        $this->customer = $customer;
        $this->seller = $seller;
        $this->saleDate = $saleDate;
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleNumber(): string
    {
        return $this->saleNumber;
    }

    public function assignNumber(int $sequence): void
    {
        $this->saleNumber = sprintf('V-%06d', $sequence);
    }

    public function getSaleDate(): \DateTimeImmutable
    {
        return $this->saleDate;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getSeller(): string
    {
        return $this->seller;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isEditable(): bool
    {
        return $this->status === 'COTIZACION';
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

    public function isIgvIncluded(): bool
    {
        return $this->igvIncluded;
    }

    public function setIgvIncluded(bool $value): void
    {
        $this->igvIncluded = $value;
    }

    public function isIgvExempt(): bool
    {
        return $this->igvExempt;
    }

    public function setIgvExempt(bool $value): void
    {
        $this->igvExempt = $value;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $value): void
    {
        $this->currency = strtoupper($value) === 'USD' ? 'USD' : 'PEN';
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $value): void
    {
        $this->channel = strtoupper($value) === 'TALLER' ? 'TALLER' : 'MOSTRADOR';
    }

    public function getRetailPaymentType(): ?string
    {
        return $this->retailPaymentType;
    }

    public function getRetailFinancialEntity(): ?string
    {
        return $this->retailFinancialEntity;
    }

    public function getRetailTcea(): ?string
    {
        return $this->retailTcea;
    }

    public function getRetailBonusYmdp(): ?string
    {
        return $this->retailBonusYmdp;
    }

    public function getRetailBonusDealer(): ?string
    {
        return $this->retailBonusDealer;
    }

    public function getRetailCampaign(): ?string
    {
        return $this->retailCampaign;
    }

    /**
     * Datos del reporte Yamaha (venta de moto). Todos opcionales.
     *
     * @param array<string, mixed> $d
     */
    public function setRetailData(array $d): void
    {
        $str = static fn (mixed $v): ?string => $v !== null && trim((string) $v) !== '' ? trim((string) $v) : null;
        $num = static fn (mixed $v): ?string => $v !== null && $v !== '' ? number_format((float) $v, 2, '.', '') : null;
        $this->retailPaymentType = $str($d['paymentType'] ?? null);
        $this->retailFinancialEntity = $str($d['financialEntity'] ?? null);
        $this->retailTcea = $num($d['tcea'] ?? null);
        $this->retailBonusYmdp = $num($d['bonusYmdp'] ?? null);
        $this->retailBonusDealer = $num($d['bonusDealer'] ?? null);
        $this->retailCampaign = $str($d['campaign'] ?? null);
    }

    public function setTotals(float $subtotal, float $igv, float $total): void
    {
        $this->subtotal = number_format($subtotal, 2, '.', '');
        $this->igv = number_format($igv, 2, '.', '');
        $this->total = number_format($total, 2, '.', '');
        $this->refreshPaymentStatus();
    }

    public function getGlobalDiscount(): string
    {
        return $this->globalDiscount;
    }

    public function setGlobalDiscount(float $amount): void
    {
        $this->globalDiscount = number_format($amount, 2, '.', '');
    }

    /** Total descontado: líneas + descuento global (para el comprobante). */
    public function getTotalDiscount(): string
    {
        $total = (float) $this->globalDiscount;
        foreach ($this->items as $item) {
            $total += (float) $item->getDiscount();
        }

        return number_format($total, 2, '.', '');
    }

    public function getDiscountAuthorizedBy(): ?string
    {
        return $this->discountAuthorizedBy;
    }

    public function getDiscountAuthorizedAt(): ?\DateTimeImmutable
    {
        return $this->discountAuthorizedAt;
    }

    public function authorizeDiscount(string $username): void
    {
        $this->discountAuthorizedBy = $username;
        $this->discountAuthorizedAt = new \DateTimeImmutable();
    }

    public function getPaidAmount(): string
    {
        return $this->paidAmount;
    }

    public function registerPaidAmount(float $amount): void
    {
        $this->paidAmount = number_format((float) $this->paidAmount + $amount, 2, '.', '');
        $this->refreshPaymentStatus();
    }

    public function getBalance(): string
    {
        return number_format((float) $this->total - (float) $this->paidAmount, 2, '.', '');
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    private function refreshPaymentStatus(): void
    {
        $paid = (float) $this->paidAmount;
        $total = (float) $this->total;
        $this->paymentStatus = match (true) {
            $paid <= 0.0 => 'PENDIENTE',
            $paid + 0.005 >= $total => 'PAGADO',
            default => 'PARCIAL',
        };
    }

    public function getReservationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->reservationExpiresAt;
    }

    public function setReservationExpiresAt(?\DateTimeImmutable $v): void
    {
        $this->reservationExpiresAt = $v;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function markCompleted(): void
    {
        $this->status = 'COMPLETADA';
        $this->completedAt = new \DateTimeImmutable();
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $v): void
    {
        $this->notes = $v;
    }

    /** @return Collection<int, SaleItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SaleItem $item): void
    {
        $this->items->add($item);
    }

    public function clearItems(): void
    {
        $this->items->clear();
    }

    /** @return Collection<int, SalePayment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }
}
