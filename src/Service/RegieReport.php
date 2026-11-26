<?php

namespace App\Service;

use App\Repository\AdEventRepository;
use App\Repository\AnnouncementRepository;
use App\Repository\HomeBannerRepository;

/**
 * « Performance » de la régie : affichages, clics et taux de clic des
 * publicités du slider et des annonces sur une période. Partagé par le
 * back-office (App\Controller\Admin\RegieStatsController) et l'application
 * mobile, pour que les deux affichent les mêmes chiffres.
 */
final class RegieReport
{
    /** Périodes proposées, en jours (0 : depuis toujours). */
    public const PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 0 => 'Depuis le début'];

    public function __construct(
        private AdEventRepository $adEventRepository,
        private HomeBannerRepository $bannerRepository,
        private AnnouncementRepository $announcementRepository,
    ) {
    }

    /**
     * @return array{days: int, banners: list<array<string, mixed>>, bannerTotals: array<string, mixed>, announcements: list<array<string, mixed>>, announcementTotals: array<string, mixed>}
     */
    public function build(int $days): array
    {
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

        return [
            'days' => $days,
            'banners' => $banners,
            'bannerTotals' => $bannerTotals,
            'announcements' => $announcements,
            'announcementTotals' => $announcementTotals,
        ];
    }

    private function ctr(int $clicks, int $impressions): ?float
    {
        return $impressions > 0 ? round($clicks / $impressions * 100, 1) : null;
    }
}
