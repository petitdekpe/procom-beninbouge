<?php

namespace App\Controller;

use App\Entity\Enum\HomeSectionType;
use App\Entity\Enum\MediaType;
use App\Repository\AnnouncementRepository;
use App\Repository\ArticleRepository;
use App\Repository\HomeSectionRepository;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private MediaRepository $mediaRepository,
        private HomeSectionRepository $homeSectionRepository,
        private AnnouncementRepository $announcementRepository,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $homeSections = $this->homeSectionRepository->findEnabledOrdered();
        $enabledTypes = array_map(static fn ($section) => $section->getType(), $homeSections);

        $heroSlides = \in_array(HomeSectionType::HeroSlider, $enabledTypes, true)
            ? $this->articleRepository->findFeaturedPublished(5)
            : [];

        $excludedIds = array_map(static fn ($article) => $article->getId(), $heroSlides);

        $actu = \in_array(HomeSectionType::LatestNews, $enabledTypes, true)
            ? $this->articleRepository->findLatestPublished(6, $excludedIds)
            : [];

        $popularArticles = \in_array(HomeSectionType::PopularArticles, $enabledTypes, true)
            ? $this->articleRepository->findMostViewedPublished(3)
            : [];

        $videos = \in_array(HomeSectionType::Videos, $enabledTypes, true)
            ? $this->mediaRepository->findLatestPublishedByType(MediaType::Video, 6)
            : [];

        $podcasts = \in_array(HomeSectionType::Podcasts, $enabledTypes, true)
            ? $this->mediaRepository->findLatestPublishedByType(MediaType::Audio, 6)
            : [];

        $categoryHighlightArticles = [];
        foreach ($homeSections as $section) {
            if (HomeSectionType::CategoryHighlight === $section->getType() && $section->getCategory()) {
                $categoryHighlightArticles[$section->getId()] = $this->articleRepository->findLatestPublishedByCategory($section->getCategory(), 3);
            }
        }

        $homeAnnouncements = \in_array(HomeSectionType::Announcements, $enabledTypes, true)
            ? $this->announcementRepository->findOnlineForHomeCard(3)
            : [];

        return $this->render('home/index.html.twig', [
            'homeAnnouncements' => $homeAnnouncements,
            // Pop-up payant : indépendant des sections, affiché dès qu'une annonce l'a acheté.
            'popupAnnouncement' => $this->announcementRepository->findRandomOnlinePopup(),
            'homeSections' => $homeSections,
            'heroSlides' => $heroSlides,
            'actu' => $actu,
            'popularArticles' => $popularArticles,
            'videos' => $videos,
            'podcasts' => $podcasts,
            'categoryHighlightArticles' => $categoryHighlightArticles,
        ]);
    }
}
