<?php

declare(strict_types=1);

namespace App\Shared\Ubigeo\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Catálogo de ubicaciones del Perú (ubigeo) servido desde archivos estáticos.
 *
 * Los datos (departamentos/provincias/distritos) viven en
 * backend/resources/ubigeo/*.json (fuente: joseluisq/ubigeos-peru). Se cargan
 * en memoria y se filtran por jerarquía (id_padre_ubigeo). Funciona offline
 * (LAN) sin depender de ninguna API externa.
 */
final class UbigeoService
{
    private string $dir;

    /** @var array<string, list<array<string, mixed>>> */
    private array $cache = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        private readonly LoggerInterface $logger,
    ) {
        $this->dir = rtrim($projectDir, '/\\').'/resources/ubigeo';
    }

    /** @return list<array{id: string, name: string}> */
    public function departments(): array
    {
        return $this->mapSort($this->load('departamentos.json'));
    }

    /** @return list<array{id: string, name: string}> */
    public function provinces(string $departmentId): array
    {
        return $this->childrenOf($this->load('provincias.json'), $departmentId);
    }

    /** @return list<array{id: string, name: string}> */
    public function districts(string $provinceId): array
    {
        return $this->childrenOf($this->load('distritos.json'), $provinceId);
    }

    /** True si los datos base están presentes (para diagnóstico). */
    public function isAvailable(): bool
    {
        return $this->load('departamentos.json') !== [];
    }

    /** @return list<array<string, mixed>> */
    private function load(string $file): array
    {
        if (isset($this->cache[$file])) {
            return $this->cache[$file];
        }
        $path = $this->dir.'/'.$file;
        if (!is_file($path)) {
            $this->logger->warning('Ubigeo: archivo de datos no encontrado', ['file' => $path]);

            return $this->cache[$file] = [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return $this->cache[$file] = is_array($data) ? $data : [];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{id: string, name: string}>
     */
    private function childrenOf(array $rows, string $parentId): array
    {
        $filtered = array_filter($rows, static fn ($r): bool => (string) ($r['id_padre_ubigeo'] ?? '') === $parentId);

        return $this->mapSort($filtered);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<array{id: string, name: string}>
     */
    private function mapSort(array $rows): array
    {
        $items = array_map(
            static fn ($r): array => ['id' => (string) ($r['id_ubigeo'] ?? ''), 'name' => (string) ($r['nombre_ubigeo'] ?? '')],
            array_values($rows),
        );
        usort($items, static fn ($a, $b): int => strcmp($a['name'], $b['name']));

        return $items;
    }
}
