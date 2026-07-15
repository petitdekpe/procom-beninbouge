<?php

namespace App\Controller\Admin;

use App\Entity\Enum\ArticleStatus;
use App\Entity\Enum\CommentStatus;
use App\Repository\ArticleImpressionRepository;
use App\Repository\ArticleRepository;
use App\Repository\ArticleShareRepository;
use App\Repository\ArticleViewRepository;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/stats')]
final class StatsController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleViewRepository $articleViewRepository,
        private ArticleShareRepository $articleShareRepository,
        private ArticleImpressionRepository $articleImpressionRepository,
        private CommentRepository $commentRepository,
    ) {
    }

    #[Route('', name: 'admin_stats_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/stats/index.html.twig', [
            'totalViews' => $this->articleViewRepository->countTotal(),
            'uniqueVisitors' => $this->articleViewRepository->countUniqueTotal(),
            'avgDuration' => $this->articleViewRepository->averageDurationTotal(),
            'viewsBySource' => $this->articleViewRepository->countBySource(),
            'totalShares' => $this->articleShareRepository->countTotal(),
            'sharesByChannel' => $this->articleShareRepository->countByChannel(),
            'topArticles' => $this->articleViewRepository->topArticles(10),
            'pendingComments' => $this->commentRepository->countByStatus(CommentStatus::Pending),
            'approvedComments' => $this->commentRepository->countByStatus(CommentStatus::Approved),
        ]);
    }

    /**
     * One row per published article, most-viewed first — the "pour
     * chaque article" breakdown. Built from a handful of GROUP BY maps
     * (one query each) merged in PHP rather than N+1 per-article queries.
     */
    #[Route('/articles', name: 'admin_stats_articles', methods: ['GET'])]
    public function articles(): Response
    {
        $viewsMap = $this->articleViewRepository->countByArticleMap();
        $uniqueMap = $this->articleViewRepository->countUniqueByArticleMap();
        $durationMap = $this->articleViewRepository->averageDurationByArticleMap();
        $shareMap = $this->articleShareRepository->countByArticleMap();
        $impressionMap = $this->articleImpressionRepository->countByArticleMap();
        $commentMap = $this->commentRepository->countApprovedByArticleMap();

        $articles = $this->articleRepository->findBy(['status' => ArticleStatus::Published], ['publishedAt' => 'DESC'], 200);

        $rows = [];
        foreach ($articles as $article) {
            $id = $article->getId();
            $views = $viewsMap[$id] ?? 0;
            $impressions = $impressionMap[$id] ?? 0;

            $rows[] = [
                'article' => $article,
                'views' => $views,
                'uniqueReaders' => $uniqueMap[$id] ?? 0,
                'avgDuration' => $durationMap[$id] ?? null,
                'shares' => $shareMap[$id] ?? 0,
                'comments' => $commentMap[$id] ?? 0,
                'impressions' => $impressions,
                'ctr' => $impressions > 0 ? round(($views / $impressions) * 100, 1) : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['views'] <=> $a['views']);

        return $this->render('admin/stats/articles.html.twig', [
            'rows' => $rows,
        ]);
    }
}
