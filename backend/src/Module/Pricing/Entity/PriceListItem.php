<?php

declare(strict_types=1);

namespace App\Module\Pricing\Entity;

use App\Module\Pricing\Repository\PriceListItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Precio de un producto dentro de una lista (Adición A4 · §24.5).
 *
 * El producto se referencia de forma polimórfica (subjectType + subjectId),
 * reutilizando la misma convención de `PriceHistory`: 'spare_part' o
 * 'motorcycle_model'. Un producto aparece a lo sumo una vez por lista.
 */
#[ORM\Entity(repositoryClass: PriceListItemRepository::class)]
#[ORM\Table(name: 'price_list_items')]
#[ORM\UniqueConstraint(name: 'uq_price_list_item', columns: ['price_list_id', 'subject_type', 'subject_id'])]
#[ORM\Index(columns: ['subject_type', 'subject_id'], name: 'idx_price_list_item_subject')]
// Índice de la FK a la lista (A4): nombre explícito para que el esquema
// coincida con la migración y `doctrine:schema:validate` quede en sync.
#[ORM\Index(columns: ['price_list_id'], name: 'IDX_price_list_items_list')]
class PriceListItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PriceList::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PriceList $priceList;

    #[ORM\Column(length: 40)]
    private string $subjectType;

    #[ORM\Column]
    private int $subjectId;

    /** Etiqueta legible del producto (código/descripción) para mostrar sin joins. */
    #[ORM\Column(length: 200)]
    private string $subjectLabel;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price;

    public function __construct(PriceList $priceList, string $subjectType, int $subjectId, string $subjectLabel, string $price)
    {
        $this->priceList = $priceList;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->subjectLabel = $subjectLabel;
        $this->price = $price;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPriceList(): PriceList
    {
        return $this->priceList;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getSubjectLabel(): string
    {
        return $this->subjectLabel;
    }

    public function setSubjectLabel(string $subjectLabel): void
    {
        $this->subjectLabel = $subjectLabel;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }
}
