<?php

declare(strict_types=1);

namespace App\Module\Payment\Dto;

use App\Module\Payment\Entity\PaymentTransaction;
use Symfony\Component\Validator\Constraints as Assert;

/** Payload de registro de una transacción de pasarela (Adición A6). */
final class PaymentTransactionPayload
{
    public function __construct(
        #[Assert\Choice(choices: PaymentTransaction::METHODS, message: 'Medio de pago inválido.')]
        public readonly string $method,

        #[Assert\Positive(message: 'El monto debe ser mayor a 0.')]
        public readonly float $amount,

        #[Assert\Length(max: 60)]
        public readonly ?string $operationNumber = null,

        #[Assert\Positive]
        public readonly ?int $saleId = null,

        #[Assert\Length(max: 30)]
        public readonly ?string $saleNumber = null,

        #[Assert\Length(max: 200)]
        public readonly ?string $customerLabel = null,

        #[Assert\Length(max: 255)]
        public readonly ?string $notes = null,
    ) {
    }
}
