<?php

declare(strict_types=1);

namespace App\Module\CashRegister\Entity;

use App\Module\CashRegister\Repository\CashSessionRepository;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sesión de caja (§13): apertura → movimientos → cierre con arqueo.
 * v1: una única caja física; solo puede existir una sesión ABIERTA a la vez.
 */
#[ORM\Entity(repositoryClass: CashSessionRepository::class)]
#[ORM\Table(name: 'cash_sessions')]
#[ORM\UniqueConstraint(name: 'uq_cash_session_number', columns: ['session_number'])]
#[ORM\Index(columns: ['status'], name: 'idx_cash_session_status')]
#[ORM\HasLifecycleCallbacks]
class CashSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $sessionNumber = '';

    #[ORM\Column(length: 100)]
    private string $openedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $openedAt;

    /** Monto inicial en efectivo. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $openingAmount;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $closedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    /** Efectivo contado físicamente al cierre (arqueo). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $countedAmount = null;

    /** Efectivo esperado según movimientos. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $expectedAmount = null;

    /** Diferencia de caja: contado - esperado (§13: debe quedar registrada). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $difference = null;

    #[ORM\Column(length: 10, options: ['default' => 'ABIERTA'])]
    private string $status = 'ABIERTA';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, CashMovement> */
    #[ORM\OneToMany(targetEntity: CashMovement::class, mappedBy: 'session')]
    private Collection $movements;

    public function __construct(string $openedBy, float $openingAmount)
    {
        $this->openedBy = $openedBy;
        $this->openedAt = new \DateTimeImmutable();
        $this->openingAmount = number_format($openingAmount, 2, '.', '');
        $this->movements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionNumber(): string
    {
        return $this->sessionNumber;
    }

    public function assignNumber(int $sequence): void
    {
        $this->sessionNumber = sprintf('CAJA-%06d', $sequence);
    }

    public function getOpenedBy(): string
    {
        return $this->openedBy;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getOpeningAmount(): string
    {
        return $this->openingAmount;
    }

    public function getClosedBy(): ?string
    {
        return $this->closedBy;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getCountedAmount(): ?string
    {
        return $this->countedAmount;
    }

    public function getExpectedAmount(): ?string
    {
        return $this->expectedAmount;
    }

    public function getDifference(): ?string
    {
        return $this->difference;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status === 'ABIERTA';
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    /** @return Collection<int, CashMovement> */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    public function close(string $closedBy, float $countedAmount, float $expectedAmount, ?string $notes): void
    {
        $this->closedBy = $closedBy;
        $this->closedAt = new \DateTimeImmutable();
        $this->countedAmount = number_format($countedAmount, 2, '.', '');
        $this->expectedAmount = number_format($expectedAmount, 2, '.', '');
        $this->difference = number_format($countedAmount - $expectedAmount, 2, '.', '');
        $this->status = 'CERRADA';
        if ($notes !== null && $notes !== '') {
            $this->notes = trim(($this->notes ?? '')."\n[Cierre] ".$notes);
        }
    }
}
