<?php

declare(strict_types=1);

namespace App\Module\Sales\Entity;

use App\Module\Inventory\Entity\SparePart;
use App\Module\Motorcycle\Entity\MotorcycleUnit;
use Doctrine\ORM\Mapping as ORM;

/**
 * Línea de venta: repuesto, unidad de motocicleta o servicio (§12).
 */
#[ORM\Entity]
#[ORM\Table(name: 'sale_items')]
class SaleItem
{
    public const TYPES = ['SPARE_PART', 'MOTORCYCLE_UNIT', 'SERVICE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sale::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Sale $sale;

    #[ORM\Column(length: 20)]
    private string $itemType;

    #[ORM\ManyToOne(targetEntity: SparePart::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SparePart $sparePart = null;

    #[ORM\ManyToOne(targetEntity: MotorcycleUnit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?MotorcycleUnit $motorcycleUnit = null;

    #[ORM\Column(length: 250)]
    private string $description;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $discount = '0.00';

    /** Descuento expresado en % del bruto de la línea (Adición A1). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $discountPercent = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $lineTotal;

    public function __construct(
        Sale $sale,
        string $itemType,
        string $description,
        int $quantity,
        float $unitPrice,
        float $discount,
        ?float $discountPercent = null,
    ) {
        $this->sale = $sale;
        $this->itemType = $itemType;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = number_format($unitPrice, 2, '.', '');
        $this->discount = number_format($discount, 2, '.', '');
        $this->discountPercent = $discountPercent !== null ? number_format($discountPercent, 2, '.', '') : null;
        $this->lineTotal = number_format($quantity * $unitPrice - $discount, 2, '.', '');
    }

    public function getDiscountPercent(): ?string
    {
        return $this->discountPercent;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): Sale
    {
        return $this->sale;
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
