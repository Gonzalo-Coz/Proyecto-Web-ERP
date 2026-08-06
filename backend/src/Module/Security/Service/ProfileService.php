<?php

declare(strict_types=1);

namespace App\Module\Security\Service;

use App\Module\Security\Dto\ChangePasswordPayload;
use App\Module\Security\Dto\ProfilePayload;
use App\Module\Security\Entity\User;
use App\Module\Security\Repository\UserRepository;
use App\Shared\Media\ImagePreset;
use App\Shared\Media\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Autoservicio del perfil del usuario autenticado (§Mejoras funcionales).
 * Controller fino → toda la lógica vive aquí (§23.2). La auditoría del cambio
 * queda registrada automáticamente por AuditListener al modificar la entidad.
 */
final class ProfileService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ImageStorageService $imageStorage,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'fullName' => $user->getFullName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'avatarUrl' => $this->imageStorage->publicUrl($user->getAvatarPath()),
            'roles' => $user->getRoles(),
            'permissions' => $user->getPermissionCodes(),
            'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function update(User $user, ProfilePayload $payload): array
    {
        $this->assertEmailAvailable($payload->email, $user->getId());

        $user->setFullName($payload->fullName);
        $user->setEmail($payload->email);
        $user->setPhone($payload->phone);

        $this->entityManager->flush();

        return $this->toArray($user);
    }

    public function changePassword(User $user, ChangePasswordPayload $payload): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $payload->currentPassword)) {
            throw new ConflictHttpException('La contraseña actual no es correcta.');
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->newPassword));
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    public function setAvatar(User $user, UploadedFile $file): array
    {
        $previous = $user->getAvatarPath();
        $newPath = $this->imageStorage->store($file, ImagePreset::AVATAR);
        $user->setAvatarPath($newPath);
        $this->entityManager->flush();

        $this->imageStorage->delete($previous); // limpia la anterior tras persistir

        return $this->toArray($user);
    }

    /** @return array<string, mixed> */
    public function removeAvatar(User $user): array
    {
        $previous = $user->getAvatarPath();
        $user->setAvatarPath(null);
        $this->entityManager->flush();

        $this->imageStorage->delete($previous);

        return $this->toArray($user);
    }

    private function assertEmailAvailable(string $email, ?int $exceptId): void
    {
        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing !== null && $existing->getId() !== $exceptId) {
            throw new ConflictHttpException(sprintf('El correo "%s" ya está registrado.', $email));
        }
    }
}
