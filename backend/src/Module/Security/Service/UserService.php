<?php

declare(strict_types=1);

namespace App\Module\Security\Service;

use App\Module\Security\Dto\UserPayload;
use App\Module\Security\Entity\User;
use App\Module\Security\Repository\RoleRepository;
use App\Module\Security\Repository\UserRepository;
use App\Shared\Media\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Lógica de negocio de administración de usuarios (§23.2: los controllers
 * solo delegan; toda la lógica vive en Services).
 */
final class UserService
{
    private const SORTABLE = ['username', 'fullName', 'email', 'createdAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ImageStorageService $imageStorage,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function list(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'username';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.'.$sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== '') {
            $qb->andWhere('LOWER(u.username) LIKE :s OR LOWER(u.fullName) LIKE :s OR LOWER(u.email) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->toArray(...), iterator_to_array($paginator, false)),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function get(int $id): array
    {
        return $this->toArray($this->find($id));
    }

    public function create(UserPayload $payload): array
    {
        if ($payload->password === null) {
            throw new ConflictHttpException('La contraseña es obligatoria al crear un usuario.');
        }
        $this->assertUnique($payload->username, $payload->email, null);

        $user = new User($payload->username, $payload->email, $payload->fullName);
        $user->setPhone($payload->phone);
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));
        $user->setActive($payload->isActive);
        $this->syncRoles($user, $payload->roleIds);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->toArray($user);
    }

    public function update(int $id, UserPayload $payload): array
    {
        $user = $this->find($id);
        $this->assertUnique($payload->username, $payload->email, $id);

        // username no se modifica: es el identificador de acceso y de auditoría
        $user->setEmail($payload->email);
        $user->setPhone($payload->phone);
        $user->setFullName($payload->fullName);
        $user->setActive($payload->isActive);
        if ($payload->password !== null && $payload->password !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));
        }
        $this->syncRoles($user, $payload->roleIds);

        $this->entityManager->flush();

        return $this->toArray($user);
    }

    /** Eliminación lógica (§23.7). */
    public function delete(int $id, string $currentUsername): void
    {
        $user = $this->find($id);
        if ($user->getUsername() === $currentUsername) {
            throw new ConflictHttpException('No puedes eliminar tu propio usuario.');
        }

        $user->markDeleted();
        $user->setActive(false);
        $this->entityManager->flush();
    }

    private function find(int $id): User
    {
        return $this->userRepository->find($id)
            ?? throw new NotFoundHttpException('Usuario no encontrado.');
    }

    private function assertUnique(string $username, ?string $email, ?int $exceptId): void
    {
        $existing = $this->userRepository->findOneByUsername($username);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El usuario "%s" ya existe.', $username));
        }
        // El correo es opcional: solo se valida unicidad si viene con valor.
        if ($email !== null && trim($email) !== '') {
            $existingEmail = $this->userRepository->findOneBy(['email' => trim($email)]);
            if ($existingEmail !== null && $existingEmail->getId() !== $exceptId) {
                throw new ConflictHttpException(sprintf('El correo "%s" ya está registrado.', $email));
            }
        }
    }

    /** @param list<int> $roleIds */
    private function syncRoles(User $user, array $roleIds): void
    {
        $user->getAssignedRoles()->clear();
        foreach (array_unique($roleIds) as $roleId) {
            $role = $this->roleRepository->find($roleId);
            if ($role !== null && !$role->isDeleted()) {
                $user->addRole($role);
            }
        }
    }

    public function toArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'avatarUrl' => $this->imageStorage->publicUrl($user->getAvatarPath()),
            'isActive' => $user->isActive(),
            'roles' => array_map(
                static fn ($r) => ['id' => $r->getId(), 'code' => $r->getCode(), 'name' => $r->getName()],
                $user->getAssignedRoles()->toArray(),
            ),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
