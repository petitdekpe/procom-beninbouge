<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use App\Repository\MediaRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\PartnerRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page « Audience », le kit média destiné aux annonceurs et partenaires,
 * sur le modèle de brands.brut.media/audience : grands chiffres, profil des
 * lecteurs, canaux, bloc de confiance, puis appel à nous contacter.
 *
 * Deux sources, aucune statistique inventée :
 *  - mesurées par le site (vues, lecteurs, articles, vidéos, newsletter),
 *    sur les 30 derniers jours quand c'est une période ;
 *  - saisies dans Réglages → Page Audience (abonnés des réseaux, profil
 *    des lecteurs), avec leur source. Une valeur vide masque son bloc.
 */
final class AudienceController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleViewRepository $articleViewRepository,
        private MediaRepository $mediaRepository,
        private NewsletterSubscriberRepository $newsletterRepository,
        private PartnerRepository $partnerRepository,
        private SiteSettingsRepository $siteSettingsRepository,
    ) {
    }

    #[Route('/audience', name: 'app_audience', methods: ['GET'])]
    public function index(): Response
    {
        $settings = $this->siteSettingsRepository->getSettings();
        $now = new \DateTimeImmutable();
        $since = $now->modify('-30 days');

        // Canaux : uniquement ceux dont un nombre a été saisi.
        $channels = array_values(array_filter([
            ['label' => 'TikTok', 'value' => $settings->getTiktokFollowers(), 'unit' => 'abonnés', 'url' => $settings->getTiktokUrl()],
            ['label' => 'Facebook', 'value' => $settings->getFacebookFollowers(), 'unit' => 'abonnés', 'url' => $settings->getFacebookUrl()],
            ['label' => 'WhatsApp', 'value' => $settings->getWhatsappMembers(), 'unit' => 'membres', 'url' => $settings->getWhatsappUrl()],
            ['label' => 'Instagram', 'value' => $settings->getInstagramFollowers(), 'unit' => 'abonnés', 'url' => $settings->getInstagramUrl()],
            ['label' => 'LinkedIn', 'value' => $settings->getLinkedinFollowers(), 'unit' => 'abonnés', 'url' => $settings->getLinkedinUrl()],
            ['label' => 'X', 'value' => $settings->getTwitterFollowers(), 'unit' => 'abonnés', 'url' => $settings->getTwitterUrl()],
            ['label' => 'YouTube', 'value' => $settings->getYoutubeSubscribers(), 'unit' => 'abonnés', 'url' => $settings->getYoutubeUrl()],
        ],static fn (array $channel): bool => null !== $channel['value'] && $channel['value'] > 0));

        $partners = array_values(array_filter(
            $this->partnerRepository->findBy(['active' => true], ['name' => 'ASC']),
            static fn ($partner): bool => (bool) $partner->getLogoUrl(),
        ));

        $averageDuration = $this->articleViewRepository->averageDurationTotal();

        return $this->render('audience/index.html.twig', [
            'settings' => $settings,
            'channels' => $channels,
            'partners' => $partners,
            'measured' => [
                'views' => $this->articleViewRepository->countBetween($since, $now),
                'readers' => $this->articleViewRepository->countUniqueBetween($since, $now),
                'minutes' => null !== $averageDuration ? max(1, (int) round($averageDuration / 60)) : null,
                'subscribers' => $this->newsletterRepository->count([]),
                'articles' => $this->articleRepository->countPublished(),
                'videos' => $this->mediaRepository->countPublicVideos(),
            ],
        ]);
    }
}
