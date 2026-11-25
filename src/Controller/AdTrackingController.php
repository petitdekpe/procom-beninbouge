<?php

namespace App\Controller;

use App\Entity\AdEvent;
use App\Entity\Enum\AdEventKind;
use App\Repository\AnnouncementRepository;
use App\Repository\HomeBannerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reçoit les affichages et clics de la régie envoyés par
 * assets/controllers/ad_tracker_controller.js (sendBeacon, même origine).
 *
 * Public et sans jeton CSRF, comme le suivi des articles : l'écriture est
 * bornée (cible existante et réellement en ligne, emplacement connu,
 * identifiant tronqué) et ignore les robots. Un événement refusé répond
 * 204 aussi : le navigateur n'a rien à en faire.
 */
final class AdTrackingController extends AbstractController
{
    /** Emplacements acceptés par type de cible. */
    private const PLACEMENTS = [
        'banner' => ['home_slider'],
        'announcement' => ['home_card', 'popup', 'list', 'detail'],
    ];

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|headless|lighthouse|facebookexternalhit|curl|wget/i';

    public function __construct(
        private HomeBannerRepository $bannerRepository,
        private AnnouncementRepository $announcementRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/regie/evenement', name: 'app_ad_event', methods: ['POST'])]
    public function event(Request $request): Response
    {
        $content = $request->getContent();
        if (\strlen($content) > 1024) {
            return new JsonResponse(null, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        if (preg_match(self::BOT_PATTERN, (string) $request->headers->get('User-Agent', ''))) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $kind = AdEventKind::tryFrom((string) ($data['kind'] ?? ''));
        $target = (string) ($data['target'] ?? '');
        $placement = (string) ($data['placement'] ?? '');
        $id = filter_var($data['id'] ?? null, \FILTER_VALIDATE_INT);

        if (null === $kind || false === $id || !\in_array($placement, self::PLACEMENTS[$target] ?? [], true)) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        $visitorId = isset($data['visitorId']) && \is_string($data['visitorId']) && '' !== $data['visitorId']
            ? mb_substr($data['visitorId'], 0, 64)
            : null;

        $event = new AdEvent($kind, $placement, $visitorId);

        if ('banner' === $target) {
            $banner = $this->bannerRepository->find($id);
            if (null === $banner || !$banner->isLive()) {
                return new Response(null, Response::HTTP_NO_CONTENT);
            }
            $event->setBanner($banner);
        } else {
            $announcement = $this->announcementRepository->find($id);
            if (null === $announcement || !$announcement->isOnline()) {
                return new Response(null, Response::HTTP_NO_CONTENT);
            }
            $event->setAnnouncement($announcement);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
