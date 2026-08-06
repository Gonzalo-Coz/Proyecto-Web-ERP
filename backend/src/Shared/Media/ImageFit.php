<?php

declare(strict_types=1);

namespace App\Shared\Media;

/** Estrategia de encuadre al redimensionar (ver ImagePreset). */
enum ImageFit
{
    /** Recorta al centro hasta llenar exactamente el marco (relación fija). */
    case COVER;
    /** Encaja dentro del marco conservando la proporción original. */
    case CONTAIN;
}
