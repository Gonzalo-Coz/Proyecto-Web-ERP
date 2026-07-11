<?php

declare(strict_types=1);

namespace App\Module\Inventory\Entity;

use App\Module\Inventory\Repository\KardexMovementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Movimiento de Kardex (§10): registro inmutable generado automáticamente
 * por cada movimiento de inventario. Nunca se edita ni elimina.
 */
#[ORM\Entity(repositoryClass: KardexMovementRepository::class, readOnly: true)]
#[ORM\Table(name: 'kardex_movements')]
#[ORM\Index(columns: ['spare_part_id', 'created_at'], name: 'idx_kardex_part_date')]
#[ORM\Index(columns: ['movement_type'], name: 'idx_kardex_type')]
class KardexMovement
{
    public const TYPES = ['COMPRA', 'VENTA', 'TALLER', 'AJUSTE', 'DEVOLUCION', 'TRANSFERENCIA'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SparePart::class)]
    #[ORM\JoinColumn(nullable: false)]
    private SparePart $sparePart;

    #[ORM\Column(length: 20)]
    private string $movementType;

    /** Cantidad con signo: positiva entra, negativa sale. */
    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $unitCost = null;

    /** Stock resultante tras aplicar el movimiento. */
    #[ORM\Column]
    private int $balanceAfter;

    /** Documento origen (ej. "COMPRA #12", "AJUSTE manual"). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        SparePart $sparePart,
        string $movementType,
        int $quantity,
        int $balanceAfter,
        ?string $unitCost,
        ?string $reference,
        ?string $notes,
        ?string $username,
    ) {
        $this->sparePart = $sparePart;
        $this->movementType = $movementType;
        $this->quantity = $quantity;
        $this->balanceAfter = $balanceAfter;
        $this->unitCost = $unitCost;
        $this->reference = $reference;
        $this->notes = $notes;
        $this->username = $username;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSparePart(): SparePart
    {
        return $this->sparePart;
    }

    public function getMovementType(): string
    {
        return $this->movementType;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
