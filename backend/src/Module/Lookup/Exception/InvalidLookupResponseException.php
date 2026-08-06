<?php

declare(strict_types=1);

namespace App\Module\Lookup\Exception;

/** El proveedor respondió algo que no se pudo interpretar (JSON inválido/incompleto). */
final class InvalidLookupResponseException extends LookupException
{
}
