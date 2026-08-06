<?php

declare(strict_types=1);

namespace App\Module\Security\Service;

use App\Module\Security\Dto\RolePayload;
use App\Module\Security\Entity\Role;
use App\Module\Security\Repository\PermissionRepository;
use App\Module\Security\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RoleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoleRepository $roleRepository,
        private readonly PermissionRepository $permissionRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $roles = $this->roleRepository->findBy([], ['name' => 'ASC']);

        return array_map($this->toArray(...), $roles);
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id));
    }

    public function create(RolePayload $payload): array
    {
        if ($this->roleRepository->findOneByCode($payload->code) !== null) {
            throw new ConflictHttpException(sprintf('El rol "%s" ya existe.', strtoupper($payload->code)));
        }

        $role = new Role($payload->code, $payload->name);
        $this->apply($role, $payload);

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $this->toArray($role);
    }

    public function update(int $id, RolePayload $payload): array
    {
        $role = $this->find($id);

        if ($role->isSuperAdmin()) {
            throw new ConflictHttpException('El rol superadministrador no es editable.');
        }

        $existing = $this->roleRepository->findOneByCode($payload->code);
        if ($existing !== null && $existing->getId() !== $id) {
            throw new ConflictHttpException(sprintf('El rol "%s" ya existe.', strtoupper($payload->code)));
        }

        $this->apply($role, $payload);
        $this->entityManager->flush();

        return $this->toArray($role);
    }

    public function delete(int $id): void
    {
        $role = $this->find($id);
        if ($role->isSuperAdmin()) {
            throw new ConflictHttpException('El rol superadministrador no puede eliminarse.');
        }

        $role->markDeleted();
        $role->setActive(false);
        $this->entityManager->flush();
    }

    private function find(int $id): Role
    {
        return $this->roleRepository->find($id)
            ?? throw new NotFoundHttpException('Rol no encontrado.');
    }

    private function apply(Role $role, RolePayload $payload): void
    {
        $role->setName($payload->name);
        $role->setDescription($payload->description);
        $role->setActive($payload->isActive);
        $role->setMaxDiscountPercent($payload->maxDiscountPercent);

        $role->getPermissions()->clear();
        foreach (array_unique($payload->permissionCodes) as $code) {
            $permission = $this->permissionRepository->findOneByCode($code);
            if ($permission !== null) {
                $role->addPermission($permission);
            }
        }
    }

    public function toArray(Role $role): array
    {
        return [
            'id' => $role->getId(),
            'code' => $role->getCode(),
            'name' => $role->getName(),
            'description' => $role->getDescription(),
            'isSuperAdmin' => $role->isSuperAdmin(),
            'isActive' => $role->isActive(),
            'maxDiscountPercent' => $role->getMaxDiscountPercent(),
            'permissionCodes' => array_map(
                static fn ($p) => $p->getCode(),
                $role->getPermissions()->toArray(),
            ),
        ];
    }
}
