<?php

declare(strict_types=1);

namespace App\Module\Security\Command;

use App\Module\Security\Entity\Permission;
use App\Module\Security\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sincroniza el catálogo de permisos con la base de datos (upsert por código).
 * Cada módulo nuevo del ERP añadirá aquí sus pantallas y acciones (§23.9).
 * Idempotente: puede ejecutarse tantas veces como sea necesario.
 *
 * Uso: php bin/console app:security:sync-permissions
 */
#[AsCommand(
    name: 'app:security:sync-permissions',
    description: 'Sincroniza el catálogo de permisos módulo/pantalla/acción',
)]
final class SyncPermissionsCommand extends Command
{
    /**
     * Catálogo oficial de permisos del ERP.
     * Formato: módulo => pantalla => [acción => nombre descriptivo].
     */
    private const CATALOG = [
        'security' => [
            'label' => 'Seguridad',
            'screens' => [
                'users' => ['label' => 'Usuarios', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
                'roles' => ['label' => 'Roles y Permisos', 'actions' => ['view', 'create', 'edit', 'delete']],
            ],
        ],
        'audit' => [
            'label' => 'Auditoría',
            'screens' => [
                'logs' => ['label' => 'Registros', 'actions' => ['view', 'export']],
            ],
        ],
        'customers' => [
            'label' => 'Clientes',
            'screens' => [
                'list' => ['label' => 'Clientes', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
            ],
        ],
        'suppliers' => [
            'label' => 'Proveedores',
            'screens' => [
                'list' => ['label' => 'Proveedores', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
            ],
        ],
        'settings' => [
            'label' => 'Configuración',
            'screens' => [
                'catalogs' => ['label' => 'Catálogos', 'actions' => ['view', 'create', 'edit', 'delete']],
            ],
        ],
    ];

    private const ACTION_LABELS = [
        'view' => 'Ver',
        'create' => 'Crear',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'approve' => 'Aprobar',
        'cancel' => 'Anular',
        'print' => 'Imprimir',
        'export' => 'Exportar',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionRepository $permissionRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = 0;
        $existing = 0;

        foreach (self::CATALOG as $module => $moduleDef) {
            foreach ($moduleDef['screens'] as $screen => $screenDef) {
                foreach ($screenDef['actions'] as $action) {
                    $code = sprintf('%s.%s.%s', $module, $screen, $action);
                    if ($this->permissionRepository->findOneByCode($code) !== null) {
                        ++$existing;
                        continue;
                    }

                    $name = sprintf(
                        '%s / %s — %s',
                        $moduleDef['label'],
                        $screenDef['label'],
                        self::ACTION_LABELS[$action] ?? ucfirst($action),
                    );
                    $this->entityManager->persist(new Permission($module, $screen, $action, $name));
                    ++$created;
                }
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('Permisos sincronizados: %d creados, %d ya existentes.', $created, $existing));

        return Command::SUCCESS;
    }
}
