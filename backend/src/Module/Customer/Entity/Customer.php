<?php

declare(strict_types=1);

namespace App\Module\Customer\Entity;

use App\Module\Customer\Repository\CustomerRepository;
use App\Module\Pricing\Entity\PriceList;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cliente (§7 del Documento Maestro): natural o jurídico según el tipo
 * de documento. Único por (tipo, número) entre registros vigentes.
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'customers')]
#[ORM\UniqueConstraint(name: 'uq_customer_document', columns: ['document_type', 'document_number'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\Index(columns: ['name'], name: 'idx_customer_name')]
// Índice de la FK a la lista de precios (A4): nombre explícito para que el
// esquema coincida con la migración y `doctrine:schema:validate` quede en sync.
#[ORM\Index(columns: ['price_list_id'], name: 'IDX_customers_price_list')]
#[ORM\HasLifecycleCallbacks]
class Customer implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    public const DOCUMENT_TYPES = ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO'];

    /**
     * Tipos de cliente con su % de descuento por defecto (lista fija).
     * El descuento se aplica automáticamente en la venta (editable).
     */
    public const CUSTOMER_TYPES = [
        'GENERAL' => ['label' => 'Público General', 'discount' => 0.0],
        'FRECUENTE' => ['label' => 'Cliente Frecuente', 'discount' => 5.0],
        'CORPORATIVO' => ['label' => 'Corporativo / Empresa', 'discount' => 8.0],
        'MAYORISTA' => ['label' => 'Mayorista', 'discount' => 10.0],
        'VIP' => ['label' => 'VIP', 'discount' => 15.0],
    ];

    /** Documento del cliente genérico "Público General" (boleta simple, sin datos). */
    public const GENERIC_DOC_NUMBER = '00000000';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15)]
    private string $documentType;

    #[ORM\Column(length: 20)]
    private string $documentNumber;

    /** Nombres y apellidos (persona natural) o razón social (jurídica). */
    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $tradeName = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $district = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $mobile = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** Tipo de cliente (clave de CUSTOMER_TYPES); determina el % de descuento. */
    #[ORM\Column(length: 20, options: ['default' => 'GENERAL'])]
    private string $customerType = 'GENERAL';

    /** Lista de precios asignada (Adición A4); null = usa la lista predeterminada / precio base. */
    #[ORM\ManyToOne(targetEntity: PriceList::class)]
    #[ORM\JoinColumn(name: 'price_list_id', nullable: true, onDelete: 'SET NULL')]
    private ?PriceList $priceList = null;

    public function __construct(string $documentType, string $documentNumber, string $name)
    {
        $this->documentType = $documentType;
        $this->documentNumber = $documentNumber;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPriceList(): ?PriceList
    {
        return $this->priceList;
    }

    public function setPriceList(?PriceList $priceList): void
    {
        $this->priceList = $priceList;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): void
    {
        $this->documentType = $documentType;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(string $documentNumber): void
    {
        $this->documentNumber = $documentNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): void
    {
        $this->district = $district;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): void
    {
        $this->province = $province;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): void
    {
        $this->department = $department;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): void
    {
        $this->mobile = $mobile;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCustomerType(): string
    {
        return $this->customerType;
    }

    public function setCustomerType(string $customerType): void
    {
        $this->customerType = isset(self::CUSTOMER_TYPES[$customerType]) ? $customerType : 'GENERAL';
    }

    /** Etiqueta legible del tipo de cliente. */
    public function getCustomerTypeLabel(): string
    {
        return self::CUSTOMER_TYPES[$this->customerType]['label'] ?? 'Público General';
    }

    /** % de descuento por defecto según el tipo de cliente. */
    public function getDiscountPercent(): float
    {
        return (float) (self::CUSTOMER_TYPES[$this->customerType]['discount'] ?? 0.0);
    }

    /** Persona jurídica cuando el documento es RUC. */
    public function isLegalEntity(): bool
    {
        return $this->documentType === 'RUC';
    }
}
