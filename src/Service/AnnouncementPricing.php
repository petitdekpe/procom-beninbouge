<?php

namespace App\Service;

use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;

/**
 * Prix d'une annonce : (tarif page + options choisies) × nombre de jours,
 * avec les tarifs journaliers saisis dans l'admin (SiteSettings). Seule
 * source de vérité du montant : le formulaire public n'affiche qu'une
 * estimation calculée en JavaScript avec ces mêmes tarifs, le montant
 * facturé est toujours recalculé ici.
 */
final class AnnouncementPricing
{
    public function __construct(
        private SiteSettingsRepository $siteSettingsRepository,
    ) {
    }

    public function quote(int $days, bool $withHomeCard, bool $withPopup): int
    {
        $settings = $this->settings();
        $daily = $settings->getAnnouncementDailyPrice()
            + ($withHomeCard ? $settings->getAnnouncementHomeCardDailyPrice() : 0)
            + ($withPopup ? $settings->getAnnouncementPopupDailyPrice() : 0);

        return $daily * $days;
    }

    public function minDays(): int
    {
        return $this->settings()->getAnnouncementMinDays();
    }

    public function maxDays(): int
    {
        return $this->settings()->getAnnouncementMaxDays();
    }

    /**
     * @return array{page: int, homeCard: int, popup: int, minDays: int, maxDays: int}
     */
    public function rates(): array
    {
        $settings = $this->settings();

        return [
            'page' => $settings->getAnnouncementDailyPrice(),
            'homeCard' => $settings->getAnnouncementHomeCardDailyPrice(),
            'popup' => $settings->getAnnouncementPopupDailyPrice(),
            'minDays' => $settings->getAnnouncementMinDays(),
            'maxDays' => $settings->getAnnouncementMaxDays(),
        ];
    }

    private function settings(): SiteSettings
    {
        return $this->siteSettingsRepository->getSettings();
    }
}
