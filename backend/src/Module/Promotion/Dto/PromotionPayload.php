<?php

declare(strict_types=1);

namespace App\Module\Promotion\Dto;

use App\Module\Promotion\Entity\Promotion;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload de promoción (Adición A5). La coherencia por tipo (DISCOUNT exige
 * porcentaje; BONUS exige producto de bonificación) la valida PromotionService.
 */
final class PromotionPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El código es obligatorio.')]
        #[Assert\Regex(pattern: '/^[A-Z0-9._-]{2,30}$/i', message: 'Código inválido (2–30, alfanumérico).')]
        public readonly string $code,

        #[Assert\NotBlank(message: 'El nombre es obligatorio.')]
        #[Assert\Length(min: 3, max: 120)]
        public readonly string $name,

        #[Assert\Choice(choices: Promotion::TYPES, message: 'Tipo de promoción inválido.')]
        public readonly string $type,

        #[Assert\NotBlank(message: 'La fecha de inicio es obligatoria.')]
        #[Assert\Date(message: 'Fecha de inicio inválida (YYYY-MM-DD).')]
        public readonly string $startDate,

        #[Assert\NotBlank(message: 'La fecha de fin es obligatoria.')]
        #[Assert\Date(message: 'Fecha de fin inválida (YYYY-MM-DD).')]
        public readonly string $endDate,

        #[Assert\Choice(choices: Promotion::SCOPES, message: 'Alcance inválido.')]
        public readonly string $scopeType = 'ALL',

        #[Assert\PositiveOrZero]
        public readonly ?float $discountPercent = null,

        #[Assert\Positive]
        public readonly ?int $scopeRefId = null,

        public readonly ?string $bonusSubjectType = null,

        #[Assert\Positive]
        public readonly ?int $bonusSubjectId = null,

        #[Assert\Positive]
        public readonly ?int $bonusQuantity = null,

        public readonly bool $isActive = true,
    ) {
    }
}
