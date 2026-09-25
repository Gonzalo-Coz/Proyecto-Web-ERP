<?php

declare(strict_types=1);

namespace App\Module\Lookup\Infrastructure\Decolecta;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Configuración de DECOLECTA (api.decolecta.com) leída de variables de entorno.
 * El token (API Key sk_...) NUNCA se escribe en el código: se toma de
 * %env(DECOLECTA_TOKEN)% (se define en Railway).
 */
final class DecolectaConfig
{
    public function __construct(
        #[Autowire('%env(DECOLECTA_TOKEN)%')] private readonly string $token,
        #[Autowire('%env(DECOLECTA_BASE_URL)%')] private readonly string $baseUrl,
        #[Autowire('%env(int:DECOLECTA_TIMEOUT)%')] private readonly int $timeout,
        #[Autowire('%env(DECOLECTA_CAINFO)%')] private readonly string $caInfo = '',
    ) {
    }

    public function token(): string
    {
        return trim($this->token);
    }

    public function hasToken(): bool
    {
        return $this->token() !== '';
    }

    public function baseUrl(): string
    {
        $url = rtrim(trim($this->baseUrl), '/');

        return $url !== '' ? $url : 'https://api.decolecta.com';
    }

    public function timeout(): int
    {
        return $this->timeout > 0 ? $this->timeout : 8;
    }

    /** Ruta al CA bundle, o null si no se configuró (se usa la de php.ini). */
    public function caInfo(): ?string
    {
        $path = trim($this->caInfo);

        return $path === '' ? null : $path;
    }

    /**
     * Vista de configuración segura para logs (token enmascarado).
     *
     * @return array<string, mixed>
     */
    public function debugSnapshot(): array
    {
        $token = $this->token();

        return [
            'baseUrl' => $this->baseUrl(),
            'timeout' => $this->timeout(),
            'caInfo' => $this->caInfo() ?? '(php.ini)',
            'tokenSet' => $token !== '',
            'tokenPreview' => $token === '' ? '(vacío)' : substr($token, 0, 8).'…'.substr($token, -4),
        ];
    }
}
