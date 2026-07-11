<?php

declare(strict_types=1);

namespace App\Module\Supplier\Entity;

use App\Module\Supplier\Repository\SupplierRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Proveedor (§8 del Documento Maestro). Único por RUC entre vigentes.
 */
#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'suppliers')]
#[ORM\UniqueConstraint(name: 'uq_supplier_ruc', columns: ['ruc'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\Index(columns: ['business_name'], name: 'idx_supplier_business_name')]
#[ORM\HasLifecycleCallbacks]
class Supplier implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 11)]
    private string $ruc;

    #[ORM\Column(length: 200)]
    private string $businessName;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $tradeName = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $contactPerson = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(string $ruc, string $businessName)
    {
        $this->ruc = $ruc;
        $this->businessName = $businessName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRuc(): string
    {
        return $this->ruc;
    }

    public function setRuc(string $ruc): void
    {
        $this->ruc = $ruc;
    }

    public function getBusinessName(): string
    {
        return $this->businessName;
    }

    public function setBusinessName(string $businessName): void
    {
        $this->businessName = $businessName;
    }

    public function getTradeName(): ?string
    {
        return $this->tradeName;
    }

    public function setTradeName(?string $tradeName): void
    {
        $this->tradeName = $tradeName;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): void
    {
        $this->contactPerson = $contactPerson;
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
