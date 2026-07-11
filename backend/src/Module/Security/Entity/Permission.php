<?php

declare(strict_types=1);

namespace App\Module\Security\Entity;

use App\Module\Security\Repository\PermissionRepository;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Permiso atómico módulo/pantalla/acción (§23.9).
 * Código canónico: "modulo.pantalla.accion" (ej. "customers.list.export").
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'permissions')]
#[ORM\UniqueConstraint(name: 'uq_permission_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class Permission
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $code;

    #[ORM\Column(length: 50)]
    private string $module;

    #[ORM\Column(length: 50)]
    private string $screen;

    #[ORM\Column(length: 30)]
    private string $action;

    /** Nombre descriptivo mostrado en la pantalla de administración de roles. */
    #[ORM\Column(length: 150)]
    private string $name;

    public function __construct(string $module, string $screen, string $action, string $name)
    {
        $this->module = $module;
        $this->screen = $screen;
        $this->action = $action;
        $this->code = sprintf('%s.%s.%s', $module, $screen, $action);
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getScreen(): string
    {
        return $this->screen;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
