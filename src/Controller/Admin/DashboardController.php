<?php

namespace App\Controller\Admin;

use App\Entity\Enum\ArticleStatus;
use App\Entity\Enum\CommentStatus;
use App\Repository\ActivityLogRepository;
use App\Repository\AnnouncementRepository;
use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\DossierRepository;
use App\Repository\MediaRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\ProjectRepository;
use App\Repository\RecapRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord de l'admin. Chaque bloc ne s'affiche (et n'est calculé)
 * que si l'utilisateur a la permission de la page correspondante :
 * « À traiter » (files d'attente du workflow, commentaires, validations,
 * annonces, messages), audience des 7 derniers jours, état des articles,
 * activité récente (administrateurs) et inventaire du contenu.
 */
#[IsGranted('admin.access')]
final class DashboardController extends AbstractController
{
    private const CHART_DAYS = 14;

    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleViewRepository $articleViewRepository,
        private CategoryRepository $categoryRepository,
        private AuthorRepository $authorRepository,
        private ProjectRepository $projectRepository,
        private MediaRepository $mediaRepository,
        private TagRepository $tagRepository,
        private DossierRepository $dossierRepository,
        private RecapRepository $recapRepository,
        private CommentRepository $commentRepository,
        private ContactMessageRepository $contactMessageRepository,
        private NewsletterSubscriberRepository $newsletterSubscriberRepository,
        private AnnouncementRepository $announcementRepository,
        private ActivityLogRepository $activityLogRepository,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $now = new \DateTimeImmutable();
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $weekAgo = $today->modify('-6 days');

        // Journal d'activité : réservé aux administrateurs. Les articles
        // concernés sont affichés par leur titre (un article supprimé
        // depuis reste affiché par son numéro).
        $activity = $this->isGranted('ROLE_ADMIN') ? $this->activityLogRepository->findLatest(8) : [];
        $activityArticleIds = [];
        foreach ($activity as $log) {
            if ('Article' === $log->getEntityType()) {
                $activityArticleIds[] = $log->getEntityId();
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'todo' => $this->todo(),
            'audience' => $this->isGranted('stats.view') ? $this->audience($today, $tomorrow, $weekAgo) : null,
            'content' => $this->isGranted('articles.write') ? [
                'byStatus' => $this->articleRepository->countByStatus(),
                'publishedWeek' => $this->articleRepository->countPublishedBetween($weekAgo, $tomorrow),
                'publishedMonth' => $this->articleRepository->countPublishedBetween(new \DateTimeImmutable('first day of this month midnight'), $tomorrow),
                'upcoming' => $this->articleRepository->findUpcomingScheduled(5),
                'recent' => $this->articleRepository->findRecentlyUpdated(6),
                'trashed' => $this->articleRepository->countTrashed(),
            ] : null,
            'newsletter' => $this->isGranted('contact.manage') ? [
                'total' => $this->newsletterSubscriberRepository->count([]),
                'week' => $this->newsletterSubscriberRepository->countSince($weekAgo),
            ] : null,
            'announcementsOnline' => $this->isGranted('announcements.manage') ? $this->announcementRepository->countOnline() : null,
            'activity' => $activity,
            'activityArticles' => $this->articleRepository->findTitlesByIds($activityArticleIds),
            'inventory' => $this->inventory(),
            'now' => $now,
        ]);
    }

    /**
     * Les files d'attente que l'utilisateur peut traiter, avec leur volume.
     *
     * @return list<array{label: string, count: int, route: string, params: array<string, string>, hint: string}>
     */
    private function todo(): array
    {
        $items = [];

        if ($this->isGranted('workflow.review')) {
            $items[] = ['label' => 'À relire', 'count' => $this->articleRepository->count(['status' => ArticleStatus::InReview]), 'route' => 'admin_workflow_review', 'params' => [], 'hint' => 'articles soumis par les journalistes'];
        }
        if ($this->isGranted('workflow.proofread')) {
            $items[] = ['label' => 'À corriger', 'count' => $this->articleRepository->count(['status' => ArticleStatus::InCorrection]), 'route' => 'admin_workflow_correction', 'params' => [], 'hint' => 'articles relus, prêts pour la correction'];
        }
        if ($this->isGranted('comments.moderate')) {
            $items[] = ['label' => 'Commentaires', 'count' => $this->commentRepository->countByStatus(CommentStatus::Pending), 'route' => 'admin_comment_index', 'params' => ['status' => CommentStatus::Pending->value], 'hint' => 'en attente de modération'];
        }
        if ($this->isGranted('validations.manage')) {
            $items[] = [
                'label' => 'Validations',
                'count' => $this->articleRepository->count(['pendingApproval' => true]) + \count($this->authorRepository->findPendingProfileChanges()),
                'route' => 'admin_validation_index',
                'params' => [],
                'hint' => 'articles et profils à approuver',
            ];
        }
        if ($this->isGranted('announcements.manage')) {
            $items[] = ['label' => 'Annonces', 'count' => $this->announcementRepository->countPendingReview(), 'route' => 'admin_announcement_index', 'params' => ['tab' => 'review'], 'hint' => 'payées, à valider sous 48 h'];
        }
        if ($this->isGranted('contact.manage')) {
            $items[] = ['label' => 'Messages', 'count' => $this->contactMessageRepository->countUnread(), 'route' => 'admin_contact_message_index', 'params' => [], 'hint' => 'non lus'];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function audience(\DateTimeImmutable $today, \DateTimeImmutable $tomorrow, \DateTimeImmutable $weekAgo): array
    {
        $yesterday = $today->modify('-1 day');
        $prevWeekStart = $weekAgo->modify('-7 days');

        $week = $this->articleViewRepository->countBetween($weekAgo, $tomorrow);
        $prevWeek = $this->articleViewRepository->countBetween($prevWeekStart, $weekAgo);

        // Courbe des vues : un point par jour, y compris les jours sans vue.
        $chartStart = $today->modify(sprintf('-%d days', self::CHART_DAYS - 1));
        $byDay = $this->articleViewRepository->countByDaySince($chartStart);
        $days = [];
        for ($day = $chartStart; $day < $tomorrow; $day = $day->modify('+1 day')) {
            $days[] = ['date' => $day, 'count' => $byDay[$day->format('Y-m-d')] ?? 0];
        }

        return [
            'today' => $this->articleViewRepository->countBetween($today, $tomorrow),
            'yesterday' => $this->articleViewRepository->countBetween($yesterday, $today),
            'week' => $week,
            'prevWeek' => $prevWeek,
            'weekTrend' => $prevWeek > 0 ? (int) round((($week - $prevWeek) / $prevWeek) * 100) : null,
            'uniqueWeek' => $this->articleViewRepository->countUniqueBetween($weekAgo, $tomorrow),
            'avgDuration' => $this->articleViewRepository->averageDurationTotal(),
            'days' => $days,
            'maxDay' => max(1, ...array_column($days, 'count')),
            'topWeek' => $this->articleViewRepository->topArticlesSince($weekAgo, 5),
        ];
    }

    /**
     * Volumes de contenu, chacun lié à sa page si l'utilisateur y a accès.
     *
     * @return list<array{label: string, count: int, route: string|null}>
     */
    private function inventory(): array
    {
        $rows = [
            ['Articles', $this->articleRepository->count([]), 'admin_article_index', 'articles.write'],
            ['Récaps', $this->recapRepository->count([]), 'admin_recap_index', 'recaps.manage'],
            ['Dossiers', $this->dossierRepository->count([]), 'admin_dossier_index', 'taxonomy.manage'],
            ['Catégories', $this->categoryRepository->count([]), 'admin_category_index', 'taxonomy.manage'],
            ['Mots-clés', $this->tagRepository->count([]), 'admin_tag_index', 'taxonomy.manage'],
            ['Médias', $this->mediaRepository->count([]), 'admin_media_index', 'media.manage'],
            ['Auteurs', $this->authorRepository->count([]), 'admin_author_index', 'authors.manage'],
            ['Projets', $this->projectRepository->count([]), 'admin_project_index', 'pages.manage'],
        ];

        return array_map(fn (array $row): array => [
            'label' => $row[0],
            'count' => $row[1],
            'route' => $this->isGranted($row[3]) ? $row[2] : null,
        ], $rows);
    }
}
