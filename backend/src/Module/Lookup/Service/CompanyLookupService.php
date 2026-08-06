<?php

declare(strict_types=1);

namespace App\Module\Lookup\Service;

use App\Module\Lookup\Dto\CompanyResult;
use App\Module\Lookup\Exception\DocumentNotFoundException;
use App\Module\Lookup\Exception\InvalidDocumentException;
use App\Module\Lookup\Exception\LookupException;
use App\Module\Lookup\Provider\DocumentLookupProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Consulta de empresas por RUC. Reutilizable por cualquier módulo (Clientes,
 * Proveedores, etc.). Valida el formato, delega en el proveedor y registra los
 * fallos técnicos. No conoce APISPERU.
 */
final class CompanyLookupService
{
    public function __construct(
        private readonly DocumentLookupProviderInterface $provider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function byRuc(string $ruc): CompanyResult
    {
        $ruc = trim($ruc);
        if (preg_match('/^\d{11}$/', $ruc) !== 1) {
            throw new InvalidDocumentException('El RUC debe tener exactamente 11 dígitos.');
        }

        try {
            return $this->provider->lookupCompany($ruc);
        } catch (DocumentNotFoundException $e) {
            throw $e;
        } catch (LookupException $e) {
            $this->logger->error('Fallo en consulta RUC', [
                'provider' => $this->provider->name(),
                'ruc' => $ruc,
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
