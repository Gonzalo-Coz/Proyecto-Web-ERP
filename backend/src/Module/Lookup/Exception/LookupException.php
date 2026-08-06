<?php

declare(strict_types=1);

namespace App\Module\Lookup\Exception;

/**
 * Excepción base de las consultas de documentos. Su mensaje es apto para
 * mostrarse al usuario (no filtra detalles internos del proveedor).
 */
class LookupException extends \RuntimeException
{
}
