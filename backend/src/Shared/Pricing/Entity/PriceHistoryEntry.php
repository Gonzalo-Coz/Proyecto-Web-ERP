<?php

declare(strict_types=1);

namespace App\Shared\Pricing\Entity;

use App\Shared\Pricing\Repository\PriceHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historial de precios (Adición A3 · §24.4).
 *
 * Registro transversal, append-only e inmutable de cada cambio de precio de
 * un elemento "vendible" (repuesto, modelo de motocicleta, etc.). Guarda el
 * motivo del cambio —dato que la auditoría genérica no captura— y una etiqueta
 * del sujeto para consultar el reporte sin joins. Un único servicio
 * (`PriceHistoryService`) lo alimenta, evitando duplicar lógica por módulo.
 */
#[ORM\Entity(readOnly: true, repositoryClass: PriceHistoryRepository::class)]
#[ORM\Table(name: 'price_history')]
#[ORM\Index(columns: ['subject_type', 'subject_id'], name: 'idx_price_history_subject')]
#[ORM\Index(columns: ['created_at'], name: 'idx_price_history_created_at')]
class PriceHistoryEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Tipo de sujeto: 'spare_part', 'motorcycle_model'. */
    #[ORM\Column(length: 40)]
    private string $subjectType;

    #[ORM\Column]
    private int $subjectId;

    /** Etiqueta legible del sujeto al momento del cambio (código/descripción). */
    #[ORM\Column(length: 200)]
    private string $subjectLabel;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $oldPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $newPrice;

    /** Motivo del cambio (opcional; lo aporta el usuario al editar). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason;

    /** Usuario que realizó el cambio (null en procesos de consola). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $username;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $subjectType,
        int $subjectId,
        string $subjectLabel,
        ?string $oldPrice,
        ?string $newPrice,
        ?string $reason,
        ?string $username,
    ) {
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->subjectLabel = $subjectLabel;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->reason = $reason;
        $this->username = $username;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getSubjectLabel(): string
    {
        return $this->subjectLabel;
    }

    public function getOldPrice(): ?string
    {
        return $this->oldPrice;
    }

    public function getNewPrice(): ?string
    {
        return $this->newPrice;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
