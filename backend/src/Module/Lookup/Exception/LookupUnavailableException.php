<?php

declare(strict_types=1);

namespace App\Module\Lookup\Exception;

/**
 * El proveedor no está disponible: error de conexión, timeout o respuesta
 * del servidor (5xx). Reintentar más tarde suele resolverlo.
 */
final class LookupUnavailableException extends LookupException
{
}
