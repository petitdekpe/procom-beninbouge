<?php

namespace App\EventSubscriber;

use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * When App\Entity\SiteSettings::$maintenanceMode is on, replaces the
 * response for every public request with a 503 page — except for
 * /admin/* (so an admin can always get back in to turn it off again) and
 * for anyone already authenticated as ROLE_ADMIN (so they can preview the
 * live site while it's down for everyone else).
 */
final class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SiteSettingsRepository $siteSettingsRepository,
        private Security $security,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api')) {
            return;
        }

        $settings = $this->siteSettingsRepository->getSettings();

        if (!$settings->isMaintenanceMode()) {
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $html = $this->twig->render('maintenance.html.twig', ['settings' => $settings]);
        $event->setResponse(new Response($html, Response::HTTP_SERVICE_UNAVAILABLE));
    }
}
