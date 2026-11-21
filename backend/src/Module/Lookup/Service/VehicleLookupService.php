<?php

declare(strict_types=1);

namespace App\Module\Lookup\Service;

use App\Module\Lookup\Dto\VehicleResult;
use App\Module\Lookup\Entity\VehicleLookup;
use App\Module\Lookup\Exception\DocumentNotFoundException;
use App\Module\Lookup\Exception\InvalidDocumentException;
use App\Module\Lookup\Exception\LookupException;
use App\Module\Lookup\Provider\VehicleLookupProviderInterface;
use App\Module\Lookup\Repository\VehicleLookupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Consulta vehicular por placa con caché PERMANENTE en base de datos.
 *
 * Flujo:
 *  1) Si la placa ya está en la tabla vehicle_lookups → se devuelve de la BD
 *     (gratis, instantáneo, no gasta cupo del proveedor; sirve aunque el plan
 *     esté agotado o el proveedor esté caído).
 *  2) Si es una placa NUEVA → se consulta al proveedor UNA sola vez y, si tiene
 *     éxito, se guarda en la BD para no volver a consultarla nunca.
 *
 * Como una placa solo gasta cupo la primera vez, con el tiempo las motos que
 * regresan al taller ya no consumen consultas.
 */
final class VehicleLookupService
{
    public function __construct(
        private readonly VehicleLookupProviderInterface $provider,
        private readonly VehicleLookupRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function byPlaca(string $placa): VehicleResult
    {
        $placa = strtoupper(preg_replace('/[\s-]/', '', trim($placa)) ?? '');
        if (preg_match('/^[A-Z0-9]{5,8}$/', $placa) !== 1) {
            throw new InvalidDocumentException('La placa debe tener entre 5 y 8 caracteres (letras y números).');
        }

        // 1) ¿Ya la tenemos guardada? → no gasta cupo ni espera a la red.
        $saved = $this->repository->findOneByPlaca($placa);
        if ($saved !== null) {
            return new VehicleResult(
                placa: $saved->getPlaca(),
                marca: $saved->getMarca(),
                modelo: $saved->getModelo(),
                color: $saved->getColor(),
                serie: $saved->getSerie(),
                motor: $saved->getMotor(),
                vin: $saved->getVin(),
                raw: ['cached' => true, 'provider' => $saved->getProvider()],
            );
        }

        // 2) Placa nueva: se consulta al proveedor UNA vez.
        try {
            $result = $this->provider->lookupVehicle($placa);
        } catch (DocumentNotFoundException $e) {
            throw $e; // resultado normal de negocio (no se cachea)
        } catch (LookupException $e) {
            $this->logger->error('Fallo en consulta de placa', [
                'provider' => $this->provider->name(),
                'placa' => $placa,
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e; // límite/timeout/caído: no se cachea, se reintenta otro día
        }

        // Éxito → se guarda para no volver a gastar cupo con esta placa.
        try {
            $row = new VehicleLookup($placa);
            $row->setData($result->marca, $result->modelo, $result->color, $result->serie, $result->motor, $result->vin, $this->provider->name());
            $this->entityManager->persist($row);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            // Si falla el guardado (p. ej. carrera con otra consulta), no se rompe la
            // respuesta: igual se devuelve el resultado obtenido del proveedor.
            $this->logger->warning('No se pudo guardar la placa en caché de BD', ['placa' => $placa, 'message' => $e->getMessage()]);
        }

        return $result;
    }
}
