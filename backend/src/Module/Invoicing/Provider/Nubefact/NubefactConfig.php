<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Provider\Nubefact;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Configuración de NubeFact leída EXCLUSIVAMENTE de variables de entorno.
 * La ruta y el token NUNCA se escriben en el código (igual que APISPERU);
 * viven en backend/.env.local.
 */
final class NubefactConfig
{
    public function __construct(
        #[Autowire('%env(NUBEFACT_URL)%')] private readonly string $url = '',
        #[Autowire('%env(NUBEFACT_TOKEN)%')] private readonly string $token = '',
        #[Autowire('%env(NUBEFACT_AMBIENTE)%')] private readonly string $ambiente = 'demo',
        #[Autowire('%env(int:NUBEFACT_TIMEOUT)%')] private readonly int $timeout = 20,
        // CA bundle opcional (clave en Windows sin curl.cainfo). Reutiliza el mismo de APISPERU.
        #[Autowire('%env(APISPERU_CAINFO)%')] private readonly string $caInfo = '',
    ) {
    }

    public function url(): string
    {
        return trim($this->url);
    }

    public function token(): string
    {
        return trim($this->token);
    }

    public function isConfigured(): bool
    {
        return $this->url() !== '' && $this->token() !== '';
    }

    public function isProduction(): bool
    {
        return strtolower(trim($this->ambiente)) === 'produccion';
    }

    public function timeout(): int
    {
        return $this->timeout > 0 ? $this->timeout : 20;
    }

    public function caInfo(): ?string
    {
        $path = trim($this->caInfo);

        return $path === '' ? null : $path;
    }

    /** @return array<string, mixed> vista segura para logs (token enmascarado). */
    public function debugSnapshot(): array
    {
        $token = $this->token();

        return [
            'url' => $this->url(),
            'ambiente' => $this->isProduction() ? 'produccion' : 'demo',
            'timeout' => $this->timeout(),
            'caInfo' => $this->caInfo() ?? '(php.ini)',
            'tokenSet' => $token !== '',
            'tokenPreview' => $token === '' ? '(vacío)' : substr($token, 0, 6).'…'.substr($token, -4),
        ];
    }
}
