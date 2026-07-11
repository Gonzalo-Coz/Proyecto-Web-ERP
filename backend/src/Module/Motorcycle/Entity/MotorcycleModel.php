<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Entity;

use App\Module\Catalog\Entity\CatalogItem;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catálogo de modelos (§9.1): ficha técnica registrada una sola vez
 * y reutilizada por todas las unidades físicas.
 */
#[ORM\Entity(repositoryClass: MotorcycleModelRepository::class)]
#[ORM\Table(name: 'motorcycle_models')]
#[ORM\UniqueConstraint(name: 'uq_moto_model', columns: ['brand_id', 'model', 'version', 'model_year'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\HasLifecycleCallbacks]
class MotorcycleModel implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Marca del catálogo configurable (type=brands). */
    #[ORM\ManyToOne(targetEntity: CatalogItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CatalogItem $brand;

    #[ORM\Column(length: 100)]
    private string $model;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $version = null;

    #[ORM\Column]
    private int $modelYear;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $engineCapacity = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $engineType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $power = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $fuelType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $transmission = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $tankCapacity = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $weight = null;

    /** Colores disponibles, separados por coma. */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $colors = null;

    #[ORM\Column(nullable: true)]
    private ?int $warrantyMonths = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $referencePrice = null;

    /** Imagen del modelo: se implementará con la infraestructura de adjuntos. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(CatalogItem $brand, string $model, int $modelYear)
    {
        $this->brand = $brand;
        $this->model = $model;
        $this->modelYear = $modelYear;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrand(): CatalogItem
    {
        return $this->brand;
    }

    public function setBrand(CatalogItem $brand): void
    {
        $this->brand = $brand;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function getModelYear(): int
    {
        return $this->modelYear;
    }

    public function setModelYear(int $modelYear): void
    {
        $this->modelYear = $modelYear;
    }

    public function getEngineCapacity(): ?string
    {
        return $this->engineCapacity;
    }

    public function setEngineCapacity(?string $value): void
    {
        $this->engineCapacity = $value;
    }

    public function getEngineType(): ?string
    {
        return $this->engineType;
    }

    public function setEngineType(?string $value): void
    {
        $this->engineType = $value;
    }

    public function getPower(): ?string
    {
        return $this->power;
    }

    public function setPower(?string $value): void
    {
        $this->power = $value;
    }

    public function getFuelType(): ?string
    {
        return $this->fuelType;
    }

    public function setFuelType(?string $value): void
    {
        $this->fuelType = $value;
    }

    public function getTransmission(): ?string
    {
        return $this->transmission;
    }

    public function setTransmission(?string $value): void
    {
        $this->transmission = $value;
    }

    public function getTankCapacity(): ?string
    {
        return $this->tankCapacity;
    }

    public function setTankCapacity(?string $value): void
    {
        $this->tankCapacity = $value;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $value): void
    {
        $this->weight = $value;
    }

    public function getColors(): ?string
    {
        return $this->colors;
    }

    public function setColors(?string $value): void
    {
        $this->colors = $value;
    }

    public function getWarrantyMonths(): ?int
    {
        return $this->warrantyMonths;
    }

    public function setWarrantyMonths(?int $value): void
    {
        $this->warrantyMonths = $value;
    }

    public function getReferencePrice(): ?string
    {
        return $this->referencePrice;
    }

    public function setReferencePrice(?string $value): void
    {
        $this->referencePrice = $value;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $value): void
    {
        $this->imagePath = $value;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getFullName(): string
    {
        return trim(sprintf(
            '%s %s %s %d',
            $this->brand->getName(),
            $this->model,
            $this->version ?? '',
            $this->modelYear,
        ));
    }
}
