<?php

namespace App\Controller;

use App\Entity\Enum\HomeSectionType;
use App\Entity\Enum\MediaType;
use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use App\Repository\HomeSectionRepository;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleViewRepository $articleViewRepository,
        private MediaRepository $mediaRepository,
        private HomeSectionRepository $homeSectionRepository,
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
            ? $this->articleRepository->findLatestPublished(5, $excludedIds)
            : [];

        $popularArticles = \in_array(HomeSectionType::PopularArticles, $enabledTypes, true)
            ? $this->articleRepository->findMostViewedPublished(4)
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
                $categoryHighlightArticles[$section->getId()] = $this->articleRepository->findLatestPublishedByCategory($section->getCategory(), 4);
            }
        }

        $stats = [
            'totalViews' => $this->articleViewRepository->countTotal(),
        ];

        return $this->render('home/index.html.twig', [
            'homeSections' => $homeSections,
            'heroSlides' => $heroSlides,
            'actu' => $actu,
            'popularArticles' => $popularArticles,
            'videos' => $videos,
            'podcasts' => $podcasts,
            'categoryHighlightArticles' => $categoryHighlightArticles,
            'stats' => $stats,
        ]);
    }
}
