<?php

declare(strict_types=1);

namespace App\Module\Lookup\Controller;

use App\Module\Lookup\Exception\DocumentNotFoundException;
use App\Module\Lookup\Exception\InvalidDocumentException;
use App\Module\Lookup\Exception\LookupAuthException;
use App\Module\Lookup\Exception\LookupException;
use App\Module\Lookup\Exception\LookupRateLimitException;
use App\Module\Lookup\Exception\LookupUnavailableException;
use App\Module\Lookup\Service\CompanyLookupService;
use App\Module\Lookup\Service\PersonLookupService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Consultas de DNI/RUC (integración de APIs externas). Controller fino: NO
 * consume APISPERU directamente, delega en los servicios reutilizables y solo
 * traduce las excepciones de dominio a códigos HTTP.
 *
 * Disponible para cualquier usuario autenticado (se usa como autocompletado en
 * los formularios de Clientes/Proveedores). El firewall ya exige autenticación.
 */
#[Route('/api/v1/lookup')]
#[OA\Tag(name: 'Consultas DNI/RUC')]
final class LookupController
{
    public function __construct(
        private readonly PersonLookupService $personLookup,
        private readonly CompanyLookupService $companyLookup,
    ) {
    }

    #[Route('/dni/{dni}', name: 'lookup_dni', methods: ['GET'])]
    #[OA\Get(summary: 'Consulta una persona por DNI')]
    public function dni(string $dni): JsonResponse
    {
        try {
            return new JsonResponse($this->personLookup->byDni($dni)->toArray());
        } catch (LookupException $e) {
            return $this->error($e);
        }
    }

    #[Route('/ruc/{ruc}', name: 'lookup_ruc', methods: ['GET'])]
    #[OA\Get(summary: 'Consulta una empresa por RUC')]
    public function ruc(string $ruc): JsonResponse
    {
        try {
            return new JsonResponse($this->companyLookup->byRuc($ruc)->toArray());
        } catch (LookupException $e) {
            return $this->error($e);
        }
    }

    private function error(LookupException $e): JsonResponse
    {
        $status = match (true) {
            $e instanceof InvalidDocumentException => Response::HTTP_UNPROCESSABLE_ENTITY,   // 422
            $e instanceof DocumentNotFoundException => Response::HTTP_NOT_FOUND,              // 404
            $e instanceof LookupRateLimitException => Response::HTTP_TOO_MANY_REQUESTS,       // 429
            $e instanceof LookupUnavailableException => Response::HTTP_SERVICE_UNAVAILABLE,   // 503
            $e instanceof LookupAuthException => Response::HTTP_BAD_GATEWAY,                  // 502 (config)
            default => Response::HTTP_BAD_GATEWAY,                                            // 502
        };

        return new JsonResponse(['message' => $e->getMessage()], $status);
    }
}
