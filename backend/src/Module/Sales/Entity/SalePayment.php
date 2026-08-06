<?php

declare(strict_types=1);

namespace App\Module\Sales\Entity;

use App\Module\Catalog\Entity\CatalogItem;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pago/cobro de una venta (CxC). Cada pago genera además su movimiento
 * de INGRESO en Caja (§12: toda venta genera movimiento de Caja).
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'sale_payments')]
#[ORM\Index(columns: ['sale_id'], name: 'idx_sale_payment_sale')]
class SalePayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sale::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private Sale $sale;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\ManyToOne(targetEntity: CatalogItem::class)]
    #[ORM\JoinColumn(name: 'payment_method_id', nullable: true)]
    private ?CatalogItem $paymentMethod = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 100)]
    private string $username;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Sale $sale, float $amount, ?CatalogItem $paymentMethod, ?string $reference, string $username)
    {
        $this->sale = $sale;
        $this->amount = number_format($amount, 2, '.', '');
        $this->paymentMethod = $paymentMethod;
        $this->reference = $reference;
        $this->username = $username;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): Sale
    {
        return $this->sale;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getPaymentMethod(): ?CatalogItem
    {
        return $this->paymentMethod;
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
