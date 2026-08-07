<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Entity;

use App\Module\Catalog\Entity\CatalogItem;
use App\Module\Purchasing\Repository\PurchaseRepository;
use App\Module\Supplier\Entity\Supplier;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compra (§11). No se elimina: se ANULA (revierte inventario y queda en historial).
 */
#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
#[ORM\Table(name: 'purchases')]
#[ORM\UniqueConstraint(name: 'uq_purchase_number', columns: ['purchase_number'])]
#[ORM\Index(columns: ['purchase_date'], name: 'idx_purchase_date')]
#[ORM\Index(columns: ['status'], name: 'idx_purchase_status')]
#[ORM\HasLifecycleCallbacks]
class Purchase
{
    public const DOCUMENT_TYPES = ['FACTURA', 'BOLETA', 'GUIA', 'OTRO'];
    public const STATUSES = ['REGISTRADA', 'ANULADA'];

    /** IGV v1: 18% fijo; pasará a Configuración centralizada (§23.10). */
    public const IGV_RATE = 0.18;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $purchaseNumber = '';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $purchaseDate;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Supplier $supplier;

    #[ORM\Column(length: 15)]
    private string $documentType;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $series = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $documentNumber = null;

    #[ORM\Column(length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[ORM\ManyToOne(targetEntity: CatalogItem::class)]
    #[ORM\JoinColumn(name: 'payment_method_id', nullable: true)]
    private ?CatalogItem $paymentMethod = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $subtotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $igv = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column(length: 15, options: ['default' => 'REGISTRADA'])]
    private string $status = 'REGISTRADA';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, PurchaseItem> */
    #[ORM\OneToMany(targetEntity: PurchaseItem::class, mappedBy: 'purchase', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(Supplier $supplier, \DateTimeImmutable $purchaseDate, string $documentType)
    {
        $this->supplier = $supplier;
        $this->purchaseDate = $purchaseDate;
        $this->documentType = $documentType;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurchaseNumber(): string
    {
        return $this->purchaseNumber;
    }

    public function assignNumber(int $sequence): void
    {
        $this->purchaseNumber = sprintf('C-%06d', $sequence);
    }

    public function getPurchaseDate(): \DateTimeImmutable
    {
        return $this->purchaseDate;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $v): void
    {
        $this->series = $v;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(?string $v): void
    {
        $this->documentNumber = $v;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $currency = strtoupper(trim($currency));
        $this->currency = in_array($currency, ['PEN', 'USD'], true) ? $currency : 'PEN';
    }

    public function getPaymentMethod(): ?CatalogItem
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?CatalogItem $v): void
    {
        $this->paymentMethod = $v;
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

    public function setTotals(float $subtotal, float $igv, float $total): void
    {
        $this->subtotal = number_format($subtotal, 2, '.', '');
        $this->igv = number_format($igv, 2, '.', '');
        $this->total = number_format($total, 2, '.', '');
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'ANULADA';
    }

    public function cancel(): void
    {
        $this->status = 'ANULADA';
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $v): void
    {
        $this->notes = $v;
    }

    /** @return Collection<int, PurchaseItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(PurchaseItem $item): void
    {
        $this->items->add($item);
    }
}
