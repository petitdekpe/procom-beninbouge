<?php

namespace App\Controller;

use App\Entity\Enum\HomeSectionType;
use App\Entity\Enum\MediaType;
use App\Repository\AnnouncementRepository;
use App\Repository\ArticleRepository;
use App\Repository\HomeBannerRepository;
use App\Repository\HomeSectionRepository;
use App\Repository\MediaRepository;
use App\Repository\RecapRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Homepage (charte 2026, V2): the order and visibility of the sections still
 * come from /admin/home-sections. The "Une" grid and the most-read list do
 * not repeat each other (the most-read query excludes the "Une" ids), but
 * "Le fil" always shows the latest published articles of the site, even if
 * they already appear above.
 */
final class HomeController extends AbstractController
{
    private const UNE_SIZE = 3;
    private const MOST_READ_SIZE = 4;
    private const FIL_SIZE = 6;
    private const RECAP_SIZE = 5;
    private const TICKER_SIZE = 6;

    public function __construct(
        private ArticleRepository $articleRepository,
        private MediaRepository $mediaRepository,
        private HomeSectionRepository $homeSectionRepository,
        private AnnouncementRepository $announcementRepository,
        private RecapRepository $recapRepository,
        private HomeBannerRepository $bannerRepository,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $homeSections = $this->homeSectionRepository->findEnabledOrdered();
        $enabledTypes = array_map(static fn ($section) => $section->getType(), $homeSections);
        $isEnabled = static fn (HomeSectionType $type): bool => \in_array($type, $enabledTypes, true);

        $shownIds = [];
        $remember = static function (array $articles) use (&$shownIds): array {
            foreach ($articles as $article) {
                $shownIds[] = $article->getId();
            }

            return $articles;
        };

        // « La une » (ex-slider) : 3 articles à la une + les plus lus dans la même grille.
        $une = $isEnabled(HomeSectionType::HeroSlider)
            ? $remember($this->articleRepository->findFeaturedPublished(self::UNE_SIZE))
            : [];

        $mostRead = $isEnabled(HomeSectionType::HeroSlider) || $isEnabled(HomeSectionType::PopularArticles)
            ? $remember($this->articleRepository->findMostViewedPublished(self::MOST_READ_SIZE, $shownIds))
            : [];

        // « Le fil » = les derniers articles publiés du site, sans exclusion :
        // un article à la une ou parmi les plus lus peut aussi y figurer.
        $fil = $isEnabled(HomeSectionType::LatestNews)
            ? $this->articleRepository->findLatestPublished(self::FIL_SIZE)
            : [];

        $categoryHighlightArticles = [];
        foreach ($homeSections as $section) {
            if (HomeSectionType::CategoryHighlight === $section->getType() && $section->getCategory()) {
                $categoryHighlightArticles[$section->getId()] = $this->articleRepository->findLatestPublishedByCategory($section->getCategory(), 3);
            }
        }

        $videos = $isEnabled(HomeSectionType::Videos)
            ? array_values(array_filter(
                $this->mediaRepository->findLatestPublishedByType(MediaType::Video, 10),
                static fn ($media) => null !== $media->getYoutubeEmbedSrc(),
            ))
            : [];

        $podcasts = $isEnabled(HomeSectionType::Podcasts)
            ? $this->mediaRepository->findLatestPublishedByType(MediaType::Audio, 3)
            : [];

        return $this->render('home/index.html.twig', [
            'homeSections' => $homeSections,
            'ticker' => $this->articleRepository->findTickerPublished(self::TICKER_SIZE),
            'une' => $une,
            'mostRead' => $mostRead,
            'fil' => $fil,
            // Les récaps sont leur propre contenu (App\Entity\Recap), sans lien avec les articles.
            'banners' => $isEnabled(HomeSectionType::Banner)
                ? $this->bannerRepository->findLiveOrdered()
                : [],
            'recaps' => $isEnabled(HomeSectionType::Recap)
                ? $this->recapRepository->findLatestPublished(self::RECAP_SIZE)
                : [],
            'categoryHighlightArticles' => $categoryHighlightArticles,
            'videos' => \array_slice($videos, 0, 5),
            'podcasts' => $podcasts,
            'homeAnnouncements' => $isEnabled(HomeSectionType::Announcements)
                ? $this->announcementRepository->findOnlineForHomeCard(3)
                : [],
            'onlineAnnouncementsCount' => $this->announcementRepository->countOnline(),
            'jobsAnnouncement' => $une ? $this->announcementRepository->findLatestOnlineWithImage() : null,
            // Pop-up payant : indépendant des sections, affiché dès qu'une annonce l'a acheté.
            'popupAnnouncements' => $this->announcementRepository->findOnlinePopups(),
        ]);
    }
}
