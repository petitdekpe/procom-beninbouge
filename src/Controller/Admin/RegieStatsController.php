<?php

namespace App\Controller\Admin;

use App\Repository\AdEventRepository;
use App\Repository\AnnouncementRepository;
use App\Repository\HomeBannerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Performance » de la régie : affichages, clics et taux de clic des
 * publicités du slider et des annonces, sur la période choisie. Les
 * événements viennent de App\Controller\AdTrackingController ; ils ne
 * couvrent que la période écoulée depuis la mise en place de la mesure.
 */
#[IsGranted('stats.view')]
#[Route('/admin/regie/statistiques')]
final class RegieStatsController extends AbstractController
{
    /** Périodes proposées, en jours (0 : depuis toujours). */
    private const PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 0 => 'Depuis le début'];

    public function __construct(
        private AdEventRepository $adEventRepository,
        private HomeBannerRepository $bannerRepository,
        private AnnouncementRepository $announcementRepository,
    ) {
    }

    #[Route('', name: 'admin_regie_stats', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $days = $request->query->getInt('jours', 30);
        if (!\array_key_exists($days, self::PERIODS)) {
            $days = 30;
        }
        $since = $days > 0 ? new \DateTimeImmutable(sprintf('-%d days', $days)) : null;

        $bannerStats = $this->adEventRepository->statsByBanner($since);
        $announcementStats = $this->adEventRepository->statsByAnnouncement($since);

        // Une ligne par publicité ayant des événements sur la période, ou en ligne (même à zéro).
        $banners = [];
        foreach ($this->bannerRepository->findAllOrdered() as $banner) {
            $stats = $bannerStats[$banner->getId()] ?? ['impressions' => 0, 'clicks' => 0, 'readers' => 0];
            if ($stats['impressions'] > 0 || $stats['clicks'] > 0 || $banner->isLive()) {
                $banners[] = ['banner' => $banner] + $stats + ['ctr' => $this->ctr($stats['clicks'], $stats['impressions'])];
            }
        }
        usort($banners, static fn (array $a, array $b): int => $b['impressions'] <=> $a['impressions']);

        $announcements = [];
        if ([] !== $announcementStats) {
            foreach ($this->announcementRepository->findBy(['id' => array_keys($announcementStats)]) as $announcement) {
                $stats = $announcementStats[$announcement->getId()];
                $announcements[] = ['announcement' => $announcement] + $stats + ['ctr' => $this->ctr($stats['clicks'], $stats['impressions'])];
            }
        }
        usort($announcements, static fn (array $a, array $b): int => ($b['impressions'] + $b['views']) <=> ($a['impressions'] + $a['views']));

        $bannerTotals = ['impressions' => 0, 'clicks' => 0];
        foreach ($banners as $row) {
            $bannerTotals['impressions'] += $row['impressions'];
            $bannerTotals['clicks'] += $row['clicks'];
        }
        $bannerTotals['ctr'] = $this->ctr($bannerTotals['clicks'], $bannerTotals['impressions']);

        $announcementTotals = ['impressions' => 0, 'clicks' => 0, 'views' => 0, 'actions' => 0];
        foreach ($announcements as $row) {
            foreach (array_keys($announcementTotals) as $key) {
                $announcementTotals[$key] += $row[$key];
            }
        }
        $announcementTotals['ctr'] = $this->ctr($announcementTotals['clicks'], $announcementTotals['impressions']);

        return $this->render('admin/regie/stats.html.twig', [
            'periods' => self::PERIODS,
            'days' => $days,
            'banners' => $banners,
            'bannerTotals' => $bannerTotals,
            'announcements' => $announcements,
            'announcementTotals' => $announcementTotals,
        ]);
    }

    private function ctr(int $clicks, int $impressions): ?float
    {
        return $impressions > 0 ? round($clicks / $impressions * 100, 1) : null;
    }
}
