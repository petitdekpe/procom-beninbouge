<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * L'application mobile reçoit toujours ses erreurs en JSON, jamais les
 * pages d'erreur HTML du site.
 */
final class MobileApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (priorité 1), qui transforme les refus en 401/403.
        return [KernelEvents::EXCEPTION => ['onException', -8]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/mobile/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof AccessDeniedException) {
            $status = 403;
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
        } else {
            return;
        }

        $message = match (true) {
            403 === $status => 'Votre rôle ne permet pas cette action.',
            404 === $status => 'Élément introuvable.',
            default => $exception->getMessage(),
        };

        $event->setResponse(new JsonResponse(['error' => $message], $status));
    }
}
