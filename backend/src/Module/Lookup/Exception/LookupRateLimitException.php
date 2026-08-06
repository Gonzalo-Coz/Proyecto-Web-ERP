<?php

declare(strict_types=1);

namespace App\Module\Lookup\Exception;

/** Se superó el límite de consultas del plan del proveedor. */
final class LookupRateLimitException extends LookupException
{
}
