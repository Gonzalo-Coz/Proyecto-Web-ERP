<?php

declare(strict_types=1);

namespace App\Module\Lookup\Exception;

/** El número de documento no tiene un formato válido (longitud/dígitos). */
final class InvalidDocumentException extends LookupException
{
}
