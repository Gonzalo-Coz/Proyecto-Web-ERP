<?php

declare(strict_types=1);

namespace App\Module\Customer\Entity;

use App\Module\Customer\Repository\CustomerTypeRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tipo de cliente administrable con su % de descuento por defecto.
 * El descuento se aplica automáticamente en la venta (editable por línea).
 */
#[ORM\Entity(repositoryClass: CustomerTypeRepository::class)]
#[ORM\Table(name: 'customer_types')]
#[ORM\UniqueConstraint(name: 'uq_customer_type_name', columns: ['name'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\HasLifecycleCallbacks]
class CustomerType implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private string $name;

    /** % de descuento por defecto (0–100). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '0.00'])]
    private string $discountPercent = '0.00';

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(string $name, float $discountPercent = 0.0)
    {
        $this->name = $name;
        $this->setDiscountPercent($discountPercent);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDiscountPercent(): float
    {
        return (float) $this->discountPercent;
    }

    public function setDiscountPercent(float $discountPercent): void
    {
        $discountPercent = max(0.0, min(100.0, $discountPercent));
        $this->discountPercent = number_format($discountPercent, 2, '.', '');
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
