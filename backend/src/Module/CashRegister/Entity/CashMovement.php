<?php

declare(strict_types=1);

namespace App\Module\CashRegister\Entity;

use App\Module\Catalog\Entity\CatalogItem;
use App\Module\CashRegister\Repository\CashMovementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Movimiento de caja (§13): inmutable, siempre dentro de una sesión abierta.
 */
#[ORM\Entity(repositoryClass: CashMovementRepository::class, readOnly: true)]
#[ORM\Table(name: 'cash_movements')]
#[ORM\Index(columns: ['session_id', 'created_at'], name: 'idx_cash_mov_session')]
class CashMovement
{
    public const TYPES = ['INGRESO', 'EGRESO'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashSession::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false)]
    private CashSession $session;

    #[ORM\Column(length: 10)]
    private string $movementType;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    /** Medio de pago (catálogo). Null = efectivo. */
    #[ORM\ManyToOne(targetEntity: CatalogItem::class)]
    #[ORM\JoinColumn(name: 'payment_method_id', nullable: true)]
    private ?CatalogItem $paymentMethod = null;

    #[ORM\Column(length: 200)]
    private string $concept;

    /** Documento origen (ej. "VENTA V-000012", "MANUAL"). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 100)]
    private string $username;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        CashSession $session,
        string $movementType,
        float $amount,
        ?CatalogItem $paymentMethod,
        string $concept,
        ?string $reference,
        string $username,
    ) {
        $this->session = $session;
        $this->movementType = $movementType;
        $this->amount = number_format($amount, 2, '.', '');
        $this->paymentMethod = $paymentMethod;
        $this->concept = $concept;
        $this->reference = $reference;
        $this->username = $username;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): CashSession
    {
        return $this->session;
    }

    public function getMovementType(): string
    {
        return $this->movementType;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getPaymentMethod(): ?CatalogItem
    {
        return $this->paymentMethod;
    }

    /** El arqueo de efectivo considera solo movimientos en efectivo. */
    public function isCash(): bool
    {
        return $this->paymentMethod === null || $this->paymentMethod->getCode() === 'CASH';
    }

    public function getConcept(): string
    {
        return $this->concept;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
