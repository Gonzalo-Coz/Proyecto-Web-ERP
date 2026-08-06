<?php

declare(strict_types=1);

namespace App\Module\Lookup\Exception;

/**
 * Credenciales del proveedor incorrectas o ausentes (token inválido / no
 * configurado). Es un problema de configuración del sistema, no del usuario.
 */
final class LookupAuthException extends LookupException
{
}
