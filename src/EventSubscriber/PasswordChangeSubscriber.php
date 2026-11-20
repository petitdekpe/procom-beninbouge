<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tant qu'un compte utilise son mot de passe temporaire
 * (User::$mustChangePassword), toute page de l'administration renvoie vers
 * le changement de mot de passe. Cette page-là et la déconnexion restent
 * accessibles.
 */
final class PasswordChangeSubscriber implements EventSubscriberInterface
{
    private const PASSWORD_ROUTE = 'admin_account_password';

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (priorité 8), qui a identifié l'utilisateur.
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        if (self::PASSWORD_ROUTE === $request->attributes->get('_route')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isMustChangePassword()) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface && [] === $session->getFlashBag()->peek('info')) {
            $session->getFlashBag()->add('info', 'Pour votre sécurité, choisissez votre propre mot de passe avant de continuer.');
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate(self::PASSWORD_ROUTE)));
    }
}
