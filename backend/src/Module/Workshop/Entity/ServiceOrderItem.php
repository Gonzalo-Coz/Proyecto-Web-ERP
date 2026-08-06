<?php

declare(strict_types=1);

namespace App\Module\Workshop\Entity;

use App\Module\Inventory\Entity\SparePart;
use Doctrine\ORM\Mapping as ORM;

/**
 * Detalle de la orden: repuesto utilizado (descuenta inventario, §14)
 * o mano de obra.
 */
#[ORM\Entity]
#[ORM\Table(name: 'service_order_items')]
class ServiceOrderItem
{
    public const TYPES = ['PART', 'LABOR'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ServiceOrder $serviceOrder;

    #[ORM\Column(length: 10)]
    private string $itemType;

    #[ORM\ManyToOne(targetEntity: SparePart::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SparePart $sparePart = null;

    #[ORM\Column(length: 250)]
    private string $description;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $lineTotal;

    public function __construct(ServiceOrder $order, string $itemType, string $description, int $quantity, float $unitPrice)
    {
        $this->serviceOrder = $order;
        $this->itemType = $itemType;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = number_format($unitPrice, 2, '.', '');
        $this->lineTotal = number_format($quantity * $unitPrice, 2, '.', '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceOrder(): ServiceOrder
    {
        return $this->serviceOrder;
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

    public function getLineTotal(): string
    {
        return $this->lineTotal;
    }
}
