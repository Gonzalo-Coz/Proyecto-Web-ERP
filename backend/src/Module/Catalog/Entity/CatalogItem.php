<?php

declare(strict_types=1);

namespace App\Module\Catalog\Entity;

use App\Module\Catalog\Repository\CatalogItemRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Elemento de catálogo configurable (§17): marcas, categorías,
 * métodos de pago y bancos administrables desde el sistema.
 * El diseño por "tipo" permite añadir catálogos nuevos sin tocar código.
 */
#[ORM\Entity(repositoryClass: CatalogItemRepository::class)]
#[ORM\Table(name: 'catalog_items')]
#[ORM\UniqueConstraint(name: 'uq_catalog_type_name', columns: ['type', 'name'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\Index(columns: ['type'], name: 'idx_catalog_type')]
#[ORM\HasLifecycleCallbacks]
class CatalogItem implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    /** Tipos de catálogo disponibles en la v1. */
    public const TYPES = ['brands', 'categories', 'payment_methods', 'banks'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(length: 100)]
    private string $name;

    /** Código estable opcional para referencias programáticas (ej. CASH, YAPE). */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(string $type, string $name)
    {
        $this->type = $type;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code !== null ? strtoupper($code) : null;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
