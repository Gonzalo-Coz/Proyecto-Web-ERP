<?php

declare(strict_types=1);

namespace App\Module\Security\Entity;

use App\Module\Security\Repository\UserRepository;
use App\Shared\Doctrine\SoftDeletableInterface;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
// Unicidad parcial: solo aplica a registros NO eliminados lógicamente,
// permitiendo reutilizar username/email de usuarios dados de baja (§23.7).
#[ORM\UniqueConstraint(name: 'uq_user_username', columns: ['username'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\UniqueConstraint(name: 'uq_user_email', columns: ['email'], options: ['where' => '(deleted_at IS NULL)'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface
{
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $username;

    #[ORM\Column(length: 150)]
    private string $email;

    #[ORM\Column(length: 150)]
    private string $fullName;

    /** Teléfono de contacto (opcional; editable desde el perfil). */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    /**
     * Ruta RELATIVA de la fotografía de perfil (p. ej. "uploads/avatars/..").
     * Nunca se almacena la imagen en BD, solo su ruta (§Almacenamiento).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    /** Hash de contraseña (nunca en claro; §23.14). */
    #[ORM\Column]
    private string $password;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class, fetch: 'EAGER')]
    #[ORM\JoinTable(name: 'user_roles')]
    private Collection $assignedRoles;

    public function __construct(string $username, string $email, string $fullName)
    {
        $this->username = $username;
        $this->email = $email;
        $this->fullName = $fullName;
        $this->assignedRoles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = ($phone === null || trim($phone) === '') ? null : trim($phone);
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): void
    {
        $this->avatarPath = $avatarPath;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /** @return Collection<int, Role> */
    public function getAssignedRoles(): Collection
    {
        return $this->assignedRoles;
    }

    public function addRole(Role $role): void
    {
        if (!$this->assignedRoles->contains($role)) {
            $this->assignedRoles->add($role);
        }
    }

    public function removeRole(Role $role): void
    {
        $this->assignedRoles->removeElement($role);
    }

    /**
     * Roles para el componente Security de Symfony (ROLE_*).
     *
     * @return non-empty-list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->assignedRoles as $role) {
            if ($role->isActive() && !$role->isDeleted()) {
                $roles[] = 'ROLE_'.$role->getCode();
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * Códigos de permiso efectivos del usuario (§23.9).
     * Un rol superadministrador devuelve el comodín "*".
     *
     * @return list<string>
     */
    public function getPermissionCodes(): array
    {
        $codes = [];
        foreach ($this->assignedRoles as $role) {
            if (!$role->isActive() || $role->isDeleted()) {
                continue;
            }
            if ($role->isSuperAdmin()) {
                return ['*'];
            }
            foreach ($role->getPermissions() as $permission) {
                $codes[$permission->getCode()] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * Límite efectivo de descuento del usuario (Adición A2):
     * NULL = sin límite (superadmin o algún rol sin límite);
     * sin roles con límite definido = 0 (no puede descontar).
     */
    public function getMaxDiscountPercent(): ?float
    {
        $max = null;
        foreach ($this->assignedRoles as $role) {
            if (!$role->isActive() || $role->isDeleted()) {
                continue;
            }
            if ($role->isSuperAdmin() || $role->getMaxDiscountPercent() === null) {
                return null; // sin límite
            }
            $max = max($max ?? 0.0, $role->getMaxDiscountPercent());
        }

        return $max ?? 0.0;
    }

    public function eraseCredentials(): void
    {
        // No se almacenan credenciales temporales.
    }
}
