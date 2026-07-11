<?php

declare(strict_types=1);

namespace App\Module\Security\Command;

use App\Module\Security\Entity\Role;
use App\Module\Security\Entity\User;
use App\Module\Security\Repository\RoleRepository;
use App\Module\Security\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crea (una sola vez) el rol ADMIN superadministrador y el primer usuario.
 * Uso: php bin/console app:security:create-admin
 */
#[AsCommand(
    name: 'app:security:create-admin',
    description: 'Crea el rol ADMIN y el usuario administrador inicial del ERP',
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('YIGM ERP — Creación del usuario administrador');

        $username = $io->ask('Nombre de usuario', 'admin', function (string $value): string {
            if (preg_match('/^[a-z0-9._-]{3,50}$/i', $value) !== 1) {
                throw new \RuntimeException('Entre 3 y 50 caracteres alfanuméricos (se admiten . _ -).');
            }

            return $value;
        });

        if ($this->userRepository->findOneByUsername($username) !== null) {
            $io->error(sprintf('El usuario "%s" ya existe.', $username));

            return Command::FAILURE;
        }

        $email = $io->ask('Correo electrónico', null, function (?string $value): string {
            if ($value === null || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new \RuntimeException('Correo electrónico inválido.');
            }

            return $value;
        });

        $fullName = $io->ask('Nombre completo', 'Administrador del Sistema');

        $password = $io->askHidden('Contraseña (mínimo 8 caracteres)', function (?string $value): string {
            if ($value === null || strlen($value) < 8) {
                throw new \RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            }

            return $value;
        });

        $role = $this->roleRepository->findOneByCode('ADMIN');
        if ($role === null) {
            $role = new Role('ADMIN', 'Administrador');
            $role->setDescription('Control total del sistema y configuración general.');
            $role->setSuperAdmin(true);
            $this->entityManager->persist($role);
            $io->note('Rol ADMIN (superadministrador) creado.');
        }

        $user = new User($username, $email, $fullName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->addRole($role);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Usuario administrador "%s" creado correctamente.', $username));

        return Command::SUCCESS;
    }
}
