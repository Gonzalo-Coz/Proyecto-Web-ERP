<?php

declare(strict_types=1);

namespace App\Module\Promotion\Entity;

use App\Module\Promotion\Repository\PromotionRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Promoción (Adición A5 · §24.3), submódulo de Ventas.
 *
 * Tipos:
 *   - DISCOUNT: aplica un porcentaje de descuento automático a las líneas
 *     cuyo producto cae dentro del alcance (reutiliza el descuento porcentual
 *     por línea de A1: el sistema lo prellena, el usuario ve el % en su cuadro).
 *   - BONUS: otorga un producto de bonificación (línea a S/ 0.00) cuando el
 *     alcance coincide con alguna línea de la venta.
 *
 * Alcance (scope): ALL, BRAND, CATEGORY (repuestos) o MODEL. El identificador
 * del alcance (scopeRefId) apunta al catálogo (marca/categoría) o al modelo.
 */
#[ORM\Entity(repositoryClass: PromotionRepository::class)]
#[ORM\Table(name: 'promotions')]
#[ORM\UniqueConstraint(name: 'uq_promotion_code', columns: ['code'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\Index(columns: ['start_date', 'end_date'], name: 'idx_promotion_dates')]
#[ORM\HasLifecycleCallbacks]
class Promotion implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    public const TYPES = ['DISCOUNT', 'BONUS'];
    public const SCOPES = ['ALL', 'BRAND', 'CATEGORY', 'MODEL'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $code;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $type;

    /** Porcentaje de descuento (solo type=DISCOUNT). */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $discountPercent = null;

    #[ORM\Column(length: 20)]
    private string $scopeType = 'ALL';

    /** ID de marca/categoría (catálogo) o de modelo, según scopeType. */
    #[ORM\Column(nullable: true)]
    private ?int $scopeRefId = null;

    /** Bonificación (solo type=BONUS): producto y cantidad regalados. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $bonusSubjectType = null;

    #[ORM\Column(nullable: true)]
    private ?int $bonusSubjectId = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $bonusLabel = null;

    #[ORM\Column(nullable: true)]
    private ?int $bonusQuantity = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(string $code, string $name, string $type, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        $this->code = $code;
        $this->name = $name;
        $this->type = $type;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getDiscountPercent(): ?string
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?string $discountPercent): void
    {
        $this->discountPercent = $discountPercent;
    }

    public function getScopeType(): string
    {
        return $this->scopeType;
    }

    public function setScopeType(string $scopeType): void
    {
        $this->scopeType = $scopeType;
    }

    public function getScopeRefId(): ?int
    {
        return $this->scopeRefId;
    }

    public function setScopeRefId(?int $scopeRefId): void
    {
        $this->scopeRefId = $scopeRefId;
    }

    public function getBonusSubjectType(): ?string
    {
        return $this->bonusSubjectType;
    }

    public function setBonusSubjectType(?string $v): void
    {
        $this->bonusSubjectType = $v;
    }

    public function getBonusSubjectId(): ?int
    {
        return $this->bonusSubjectId;
    }

    public function setBonusSubjectId(?int $v): void
    {
        $this->bonusSubjectId = $v;
    }

    public function getBonusLabel(): ?string
    {
        return $this->bonusLabel;
    }

    public function setBonusLabel(?string $v): void
    {
        $this->bonusLabel = $v;
    }

    public function getBonusQuantity(): ?int
    {
        return $this->bonusQuantity;
    }

    public function setBonusQuantity(?int $v): void
    {
        $this->bonusQuantity = $v;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): void
    {
        $this->endDate = $endDate;
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
