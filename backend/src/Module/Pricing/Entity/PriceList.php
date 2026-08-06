<?php

declare(strict_types=1);

namespace App\Module\Pricing\Entity;

use App\Module\Pricing\Repository\PriceListRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lista de precios (Adición A4 · §24.5).
 *
 * Cabecera de una lista comercial. Los precios concretos por producto viven en
 * PriceListItem. Una lista puede marcarse como predeterminada para clientes sin
 * lista asignada. La resolución del precio de venta la centraliza PriceResolver.
 */
#[ORM\Entity(repositoryClass: PriceListRepository::class)]
#[ORM\Table(name: 'price_lists')]
#[ORM\UniqueConstraint(name: 'uq_price_list_code', columns: ['code'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\HasLifecycleCallbacks]
class PriceList implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $code;

    #[ORM\Column(length: 100)]
    private string $name;

    /** Lista aplicada a clientes sin lista propia. Solo una debería estar activa. */
    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, PriceListItem> */
    #[ORM\OneToMany(mappedBy: 'priceList', targetEntity: PriceListItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /** @return Collection<int, PriceListItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(PriceListItem $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }
    }

    public function clearItems(): void
    {
        $this->items->clear();
    }
}
