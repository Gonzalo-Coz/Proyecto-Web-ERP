<?php

declare(strict_types=1);

namespace App\Module\Lookup\Service;

use App\Module\Lookup\Dto\VehicleResult;
use App\Module\Lookup\Exception\DocumentNotFoundException;
use App\Module\Lookup\Exception\InvalidDocumentException;
use App\Module\Lookup\Exception\LookupException;
use App\Module\Lookup\Provider\VehicleLookupProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Consulta vehicular por placa. Valida el formato, normaliza la placa
 * (mayúsculas, sin guiones ni espacios), delega en el proveedor (interfaz) y
 * registra los fallos técnicos. No conoce Factiliza.
 */
final class VehicleLookupService
{
    public function __construct(
        private readonly VehicleLookupProviderInterface $provider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function byPlaca(string $placa): VehicleResult
    {
        $placa = strtoupper(preg_replace('/[\s-]/', '', trim($placa)) ?? '');
        if (preg_match('/^[A-Z0-9]{5,8}$/', $placa) !== 1) {
            throw new InvalidDocumentException('La placa debe tener entre 5 y 8 caracteres (letras y números).');
        }

        try {
            return $this->provider->lookupVehicle($placa);
        } catch (DocumentNotFoundException $e) {
            throw $e; // resultado normal de negocio
        } catch (LookupException $e) {
            $this->logger->error('Fallo en consulta de placa', [
                'provider' => $this->provider->name(),
                'placa' => $placa,
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
