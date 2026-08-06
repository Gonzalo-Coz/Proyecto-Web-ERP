<?php

declare(strict_types=1);

namespace App\Shared\Media;

/**
 * Presets de imagen del ERP (§Gestión de imágenes del Documento Maestro).
 *
 * Cada tipo de imagen tiene sus dimensiones recomendadas y su carpeta,
 * de modo que un único servicio (ImageStorageService) sirva a todos los
 * módulos sin duplicar lógica ni criterios de optimización.
 *
 * Modo de ajuste:
 *   - COVER: recorta al centro hasta llenar el marco (relación fija, sin deformar).
 *   - CONTAIN: encaja dentro del marco conservando proporción (puede no llenar).
 */
enum ImagePreset: string
{
    case AVATAR = 'avatar';
    case PRODUCT = 'product';
    case MOTORCYCLE = 'motorcycle';
    case LOGO = 'logo';
    case DOCUMENT = 'document';

    /** Carpeta relativa dentro de public/uploads. */
    public function category(): string
    {
        return match ($this) {
            self::AVATAR => 'avatars',
            self::PRODUCT => 'products',
            self::MOTORCYCLE => 'motorcycles',
            self::LOGO => 'brand',
            self::DOCUMENT => 'documents',
        };
    }

    /** @return array{0:int,1:int} ancho y alto máximos en píxeles */
    public function dimensions(): array
    {
        return match ($this) {
            self::AVATAR => [512, 512],
            self::PRODUCT => [1024, 1024],
            self::MOTORCYCLE => [1600, 1200],
            self::LOGO => [600, 600],
            self::DOCUMENT => [2000, 2000],
        };
    }

    /** Estrategia de encuadre. */
    public function fit(): ImageFit
    {
        return match ($this) {
            self::AVATAR => ImageFit::COVER,
            self::LOGO => ImageFit::CONTAIN,
            default => ImageFit::CONTAIN,
        };
    }
}
