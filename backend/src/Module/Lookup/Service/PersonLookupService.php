<?php

declare(strict_types=1);

namespace App\Module\Lookup\Service;

use App\Module\Lookup\Dto\PersonResult;
use App\Module\Lookup\Exception\DocumentNotFoundException;
use App\Module\Lookup\Exception\InvalidDocumentException;
use App\Module\Lookup\Exception\LookupException;
use App\Module\Lookup\Provider\DocumentLookupProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Consulta de personas por DNI. Reutilizable por cualquier módulo (Clientes,
 * etc.). Valida el formato, delega en el proveedor (interfaz) y registra los
 * fallos técnicos. No conoce APISPERU.
 */
final class PersonLookupService
{
    public function __construct(
        private readonly DocumentLookupProviderInterface $provider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function byDni(string $dni): PersonResult
    {
        $dni = trim($dni);
        if (preg_match('/^\d{8}$/', $dni) !== 1) {
            throw new InvalidDocumentException('El DNI debe tener exactamente 8 dígitos.');
        }

        try {
            return $this->provider->lookupPerson($dni);
        } catch (DocumentNotFoundException $e) {
            throw $e; // resultado normal de negocio: no se registra como error técnico
        } catch (LookupException $e) {
            $this->logger->error('Fallo en consulta DNI', [
                'provider' => $this->provider->name(),
                'dni' => $dni,
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
