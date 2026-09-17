<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Convierte las excepciones de la API en JSON claro. Para errores 4xx
 * (Conflict, Unprocessable, NotFound, etc.) expone el MENSAJE real en "detail",
 * para que el frontend muestre el motivo (ej. "stock insuficiente de R-0077")
 * en vez de un genérico "Conflict". Los 5xx no exponen el mensaje (seguridad).
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Prioridad alta para responder antes que el handler por defecto.
        return [KernelEvents::EXCEPTION => ['onException', 64]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return; // solo la API en JSON
        }

        $throwable = $event->getThrowable();

        // Solo errores de usuario (4xx): se expone el mensaje real en "detail".
        // Los 5xx los sigue manejando Symfony (con su log), no se tocan aquí.
        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $event->setResponse(new JsonResponse([
                'title' => Response::$statusTexts[$status] ?? 'Error',
                'status' => $status,
                'detail' => $throwable->getMessage(),
            ], $status, $throwable->getHeaders()));
        }
    }
}
