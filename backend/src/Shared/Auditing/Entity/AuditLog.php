<?php

declare(strict_types=1);

namespace App\Shared\Auditing\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro de auditoría (§18 y §23.6 del Documento Maestro).
 * Escrito automáticamente por AuditListener; nunca se modifica ni elimina.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['entity_class', 'entity_id'], name: 'idx_audit_entity')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_created_at')]
#[ORM\Index(columns: ['username'], name: 'idx_audit_username')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Usuario que ejecutó la acción (null en procesos de consola). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    /** Módulo de negocio afectado (derivado del namespace de la entidad). */
    #[ORM\Column(length: 50)]
    private string $module;

    #[ORM\Column(length: 255)]
    private string $entityClass;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityId = null;

    /** create | update | delete */
    #[ORM\Column(length: 20)]
    private string $action;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValues = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
