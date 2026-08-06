<?php

declare(strict_types=1);

namespace App\Module\Lookup\Exception;

/** El documento consultado no existe o no fue encontrado por el proveedor. */
final class DocumentNotFoundException extends LookupException
{
}
