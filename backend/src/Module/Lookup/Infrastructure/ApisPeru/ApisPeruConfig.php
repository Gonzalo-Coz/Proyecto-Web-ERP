<?php

declare(strict_types=1);

namespace App\Module\Lookup\Infrastructure\ApisPeru;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Configuración de APISPERU leída EXCLUSIVAMENTE de variables de entorno.
 * El token NUNCA se escribe en el código; se toma de %env(APISPERU_TOKEN)%.
 */
final class ApisPeruConfig
{
    public function __construct(
        #[Autowire('%env(APISPERU_TOKEN)%')] private readonly string $token,
        #[Autowire('%env(APISPERU_BASE_URL)%')] private readonly string $baseUrl,
        #[Autowire('%env(int:APISPERU_TIMEOUT)%')] private readonly int $timeout,
        // Ruta opcional a un CA bundle (cacert.pem). Si se define, se usa para
        // verificar el certificado TLS sin depender de la config global de php.ini
        // (clave en Windows, donde PHP no trae un CA bundle configurado).
        // Nota: se usa %env(APISPERU_CAINFO)% directo (definido vacío en .env)
        // en vez de default:: — este último devuelve null y rompía el tipo string.
        #[Autowire('%env(APISPERU_CAINFO)%')] private readonly string $caInfo = '',
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
        return rtrim($this->baseUrl, '/');
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

