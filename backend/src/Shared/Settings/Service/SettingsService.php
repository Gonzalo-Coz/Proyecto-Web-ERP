<?php

declare(strict_types=1);

namespace App\Shared\Settings\Service;

use App\Shared\Settings\Entity\Setting;
use App\Shared\Settings\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Configuración del sistema (§17, §23.10): valores por defecto en código,
 * sobreescritos por lo administrado en BD desde la pantalla de Configuración.
 */
final class SettingsService
{
    /** Claves reconocidas y sus valores por defecto. */
    public const DEFAULTS = [
        'company.name' => 'Yamaha Integral Global Motors',
        'company.trade_name' => 'YIGM',
        'company.ruc' => '20000000001',
        'company.address' => '',
        'company.phone' => '',
        'company.email' => '',
        'tax.igv_rate' => '18',
        'sales.reservation_days' => '7',
    ];

    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, string|null> */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = array_merge(self::DEFAULTS, $this->repository->allAsMap());
        }

        return $this->cache;
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /** Tasa de IGV como fracción (18 → 0.18). */
    public function igvRate(): float
    {
        return ((float) ($this->get('tax.igv_rate') ?? '18')) / 100;
    }

    /** @param array<string, string|null> $values */
    public function update(array $values): array
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                throw new UnprocessableEntityHttpException(sprintf('Clave de configuración desconocida: %s.', $key));
            }
        }

        if (isset($values['tax.igv_rate'])) {
            $rate = (float) $values['tax.igv_rate'];
            if ($rate < 0 || $rate > 30) {
                throw new UnprocessableEntityHttpException('El IGV debe estar entre 0 y 30 (%).');
            }
        }
        if (isset($values['company.ruc']) && preg_match('/^(10|15|17|20)\d{9}$/', (string) $values['company.ruc']) !== 1) {
            throw new UnprocessableEntityHttpException('El RUC de la empresa no tiene un formato válido.');
        }

        $existing = [];
        foreach ($this->repository->findAll() as $setting) {
            $existing[$setting->getKey()] = $setting;
        }

        foreach ($values as $key => $value) {
            if (isset($existing[$key])) {
                $existing[$key]->setValue($value !== null ? (string) $value : null);
            } else {
                $this->entityManager->persist(new Setting($key, $value !== null ? (string) $value : null));
            }
        }

        $this->entityManager->flush();
        $this->cache = null;

        return $this->all();
    }
}
