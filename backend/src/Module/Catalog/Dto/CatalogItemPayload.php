<?php

declare(strict_types=1);

namespace App\Module\Catalog\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CatalogItemPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'El nombre es obligatorio.')]
        #[Assert\Length(min: 2, max: 100)]
        public readonly string $name,

        #[Assert\Length(max: 30)]
        #[Assert\Regex(pattern: '/^[A-Za-z0-9_-]*$/', message: 'El código solo admite letras, números, guion y guion bajo.')]
        public readonly ?string $code = null,

        public readonly bool $isActive = true,
    ) {
    }
}
