<?php

declare(strict_types=1);

namespace App\Module\Inventory\Entity;

use App\Module\Catalog\Entity\CatalogItem;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Motorcycle\Entity\MotorcycleModel;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Repuesto (§10): un solo registro por repuesto, asociado a uno o varios
 * modelos mediante compatibilidades (nunca duplicado por modelo).
 * Stock de almacén único en v1; al implementar multialmacén se extraerá
 * a una tabla propia (decisión registrada en bitácora).
 */
#[ORM\Entity(repositoryClass: SparePartRepository::class)]
#[ORM\Table(name: 'spare_parts')]
#[ORM\UniqueConstraint(name: 'uq_part_internal_code', columns: ['internal_code'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\UniqueConstraint(name: 'uq_part_code', columns: ['part_code'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\Index(columns: ['barcode'], name: 'idx_part_barcode')]
#[ORM\Index(columns: ['description'], name: 'idx_part_description')]
#[ORM\HasLifecycleCallbacks]
class SparePart implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $internalCode;

    /** Código de repuesto del fabricante (ej. 5SL-E3440-00). */
    #[ORM\Column(length: 40)]
    private string $partCode;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(length: 200)]
    private string $description;

    #[ORM\ManyToOne(targetEntity: CatalogItem::class)]
    #[ORM\JoinColumn(name: 'brand_id', nullable: true)]
    private ?CatalogItem $brand = null;

    #[ORM\ManyToOne(targetEntity: CatalogItem::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true)]
    private ?CatalogItem $category = null;

    #[ORM\Column(length: 20, options: ['default' => 'UNIDAD'])]
    private string $unitOfMeasure = 'UNIDAD';

    /** @var Collection<int, MotorcycleModel> Compatibilidades (§10). */
    #[ORM\ManyToMany(targetEntity: MotorcycleModel::class)]
    #[ORM\JoinTable(name: 'spare_part_compatibility')]
    private Collection $compatibleModels;

    // --- Inventario (almacén único v1) ---

    #[ORM\Column(options: ['default' => 0])]
    private int $stock = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $minStock = 0;

    #[ORM\Column(nullable: true)]
    private ?int $maxStock = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $salePrice = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastPurchaseAt = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(string $internalCode, string $partCode, string $description)
    {
        $this->internalCode = strtoupper($internalCode);
        $this->partCode = strtoupper($partCode);
        $this->description = $description;
        $this->compatibleModels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInternalCode(): string
    {
        return $this->internalCode;
    }

    public function setInternalCode(string $v): void
    {
        $this->internalCode = strtoupper($v);
    }

    public function getPartCode(): string
    {
        return $this->partCode;
    }

    public function setPartCode(string $v): void
    {
        $this->partCode = strtoupper($v);
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $v): void
    {
        $this->barcode = $v;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $v): void
    {
        $this->description = $v;
    }

    public function getBrand(): ?CatalogItem
    {
        return $this->brand;
    }

    public function setBrand(?CatalogItem $v): void
    {
        $this->brand = $v;
    }

    public function getCategory(): ?CatalogItem
    {
        return $this->category;
    }

    public function setCategory(?CatalogItem $v): void
    {
        $this->category = $v;
    }

    public function getUnitOfMeasure(): string
    {
        return $this->unitOfMeasure;
    }

    public function setUnitOfMeasure(string $v): void
    {
        $this->unitOfMeasure = $v;
    }

    /** @return Collection<int, MotorcycleModel> */
    public function getCompatibleModels(): Collection
    {
        return $this->compatibleModels;
    }

    public function clearCompatibleModels(): void
    {
        $this->compatibleModels->clear();
    }

    public function addCompatibleModel(MotorcycleModel $model): void
    {
        if (!$this->compatibleModels->contains($model)) {
            $this->compatibleModels->add($model);
        }
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    /** Solo StockService debe modificar el stock (regla: sin stock negativo). */
    public function applyStockChange(int $delta): void
    {
        $newStock = $this->stock + $delta;
        if ($newStock < 0) {
            throw new \DomainException(sprintf(
                'Stock insuficiente de %s: disponible %d, solicitado %d.',
                $this->internalCode,
                $this->stock,
                abs($delta),
            ));
        }
        $this->stock = $newStock;
    }

    public function getMinStock(): int
    {
        return $this->minStock;
    }

    public function setMinStock(int $v): void
    {
        $this->minStock = max(0, $v);
    }

    public function getMaxStock(): ?int
    {
        return $this->maxStock;
    }

    public function setMaxStock(?int $v): void
    {
        $this->maxStock = $v;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(?string $v): void
    {
        $this->purchasePrice = $v;
    }

    public function getSalePrice(): ?string
    {
        return $this->salePrice;
    }

    public function setSalePrice(?string $v): void
    {
        $this->salePrice = $v;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $v): void
    {
        $this->location = $v;
    }

    public function getLastPurchaseAt(): ?\DateTimeImmutable
    {
        return $this->lastPurchaseAt;
    }

    public function setLastPurchaseAt(?\DateTimeImmutable $v): void
    {
        $this->lastPurchaseAt = $v;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $v): void
    {
        $this->isActive = $v;
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->minStock;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }
}
