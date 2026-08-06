<?php

declare(strict_types=1);

namespace App\Module\Payment\Entity;

use App\Module\Payment\Repository\PaymentTransactionRepository;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Transacción de pasarela de pago (Adición A6 · §24.8).
 *
 * Registro estructurado de un intento/cobro por medios digitales (Yape, Plin,
 * tarjeta, transferencia…). En v1 lo procesa `ManualGateway` y un operador lo
 * valida. Se vincula de forma suelta a la venta (número + id) para no acoplar
 * módulos ni alterar la lógica de Ventas/Caja.
 */
#[ORM\Entity(repositoryClass: PaymentTransactionRepository::class)]
#[ORM\Table(name: 'payment_transactions')]
#[ORM\Index(columns: ['status'], name: 'idx_payment_tx_status')]
#[ORM\Index(columns: ['sale_id'], name: 'idx_payment_tx_sale')]
#[ORM\HasLifecycleCallbacks]
class PaymentTransaction
{
    use TimestampableTrait;

    public const METHODS = ['YAPE', 'PLIN', 'CARD', 'TRANSFER', 'EFECTIVO', 'OTHER'];
    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'VOIDED'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Vínculo suelto a la venta (opcional). */
    #[ORM\Column(nullable: true)]
    private ?int $saleId = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $saleNumber = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $customerLabel = null;

    #[ORM\Column(length: 20)]
    private string $method;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[ORM\Column(length: 20, options: ['default' => 'PENDING'])]
    private string $status = 'PENDING';

    /** Adaptador de pasarela que procesó la operación. */
    #[ORM\Column(length: 40)]
    private string $gateway;

    /** Número de operación del voucher / respuesta de la pasarela. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $operationNumber = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $responsePayload = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $validatedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    public function __construct(string $method, string $amount, string $gateway)
    {
        $this->method = $method;
        $this->amount = $amount;
        $this->gateway = $gateway;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleId(): ?int
    {
        return $this->saleId;
    }

    public function setSaleId(?int $saleId): void
    {
        $this->saleId = $saleId;
    }

    public function getSaleNumber(): ?string
    {
        return $this->saleNumber;
    }

    public function setSaleNumber(?string $saleNumber): void
    {
        $this->saleNumber = $saleNumber;
    }

    public function getCustomerLabel(): ?string
    {
        return $this->customerLabel;
    }

    public function setCustomerLabel(?string $customerLabel): void
    {
        $this->customerLabel = $customerLabel;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function setGateway(string $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function getOperationNumber(): ?string
    {
        return $this->operationNumber;
    }

    public function setOperationNumber(?string $operationNumber): void
    {
        $this->operationNumber = $operationNumber;
    }

    /** @return array<string, mixed>|null */
    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    /** @param array<string, mixed>|null $responsePayload */
    public function setResponsePayload(?array $responsePayload): void
    {
        $this->responsePayload = $responsePayload;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getValidatedBy(): ?string
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?string $validatedBy): void
    {
        $this->validatedBy = $validatedBy;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): void
    {
        $this->validatedAt = $validatedAt;
    }
}
