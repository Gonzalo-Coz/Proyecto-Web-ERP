<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Entity;

use App\Module\Inventory\Entity\SparePart;
use App\Module\Motorcycle\Entity\MotorcycleUnit;
use Doctrine\ORM\Mapping as ORM;

/**
 * Línea de compra: repuesto (cantidad) o unidad de motocicleta (única).
 */
#[ORM\Entity]
#[ORM\Table(name: 'purchase_items')]
class PurchaseItem
{
    public const TYPES = ['SPARE_PART', 'MOTORCYCLE_UNIT'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Purchase $purchase;

    #[ORM\Column(length: 20)]
    private string $itemType;

    #[ORM\ManyToOne(targetEntity: SparePart::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SparePart $sparePart = null;

    #[ORM\ManyToOne(targetEntity: MotorcycleUnit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?MotorcycleUnit $motorcycleUnit = null;

    /** Descripción congelada al momento de la compra (histórico estable). */
    #[ORM\Column(length: 250)]
    private string $description;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $discount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $lineTotal;

    public function __construct(
        Purchase $purchase,
        string $itemType,
        string $description,
        int $quantity,
        float $unitPrice,
        float $discount,
    ) {
        $this->purchase = $purchase;
        $this->itemType = $itemType;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = number_format($unitPrice, 2, '.', '');
        $this->discount = number_format($discount, 2, '.', '');
        $this->lineTotal = number_format($quantity * $unitPrice - $discount, 2, '.', '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurchase(): Purchase
    {
        return $this->purchase;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function getSparePart(): ?SparePart
    {
        return $this->sparePart;
    }

    public function setSparePart(?SparePart $v): void
    {
        $this->sparePart = $v;
    }

    public function getMotorcycleUnit(): ?MotorcycleUnit
    {
        return $this->motorcycleUnit;
    }

    public function setMotorcycleUnit(?MotorcycleUnit $v): void
    {
        $this->motorcycleUnit = $v;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function getLineTotal(): string
    {
        return $this->lineTotal;
    }
}
