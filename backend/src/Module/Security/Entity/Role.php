<?php

declare(strict_types=1);

namespace App\Module\Security\Entity;

use App\Module\Security\Repository\RoleRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rol dinámico administrable desde el sistema (§1.5, §23.9):
 * nuevos roles y asignación de permisos sin modificar código fuente.
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'roles')]
#[ORM\UniqueConstraint(name: 'uq_role_code', columns: ['code'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\HasLifecycleCallbacks]
class Role implements SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Código estable en mayúsculas (ej. ADMIN, VENDEDOR, CAJERO). */
    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Un rol superadministrador concede TODOS los permisos presentes y
     * futuros sin necesidad de asignarlos uno a uno.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isSuperAdmin = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /**
     * Límite de descuento en ventas para este rol (Adición A2 / 24.2).
     * NULL = sin límite. Configurable desde la pantalla de Roles (§23.9).
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $maxDiscountPercent = null;

    /** @var Collection<int, Permission> */
    #[ORM\ManyToMany(targetEntity: Permission::class, fetch: 'EAGER')]
    #[ORM\JoinTable(name: 'role_permissions')]
    private Collection $permissions;

    public function __construct(string $code, string $name)
    {
        $this->code = strtoupper($code);
        $this->name = $name;
        $this->permissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function setSuperAdmin(bool $isSuperAdmin): void
    {
        $this->isSuperAdmin = $isSuperAdmin;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getMaxDiscountPercent(): ?float
    {
        return $this->maxDiscountPercent !== null ? (float) $this->maxDiscountPercent : null;
    }

    public function setMaxDiscountPercent(?float $value): void
    {
        $this->maxDiscountPercent = $value !== null ? number_format($value, 2, '.', '') : null;
    }

    /** @return Collection<int, Permission> */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): void
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }
    }

    public function removePermission(Permission $permission): void
    {
        $this->permissions->removeElement($permission);
    }
}
