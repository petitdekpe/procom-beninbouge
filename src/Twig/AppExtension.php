<?php

namespace App\Twig;

use App\Entity\Article;
use App\Entity\Enum\HomeSectionType;
use App\Entity\Enum\MediaLicense;
use App\Entity\Enum\ProjectProgress;
use App\Entity\Enum\ProjectType;
use App\Entity\Page;
use App\Entity\SiteSettings;
use App\Repository\ArticleViewRepository;
use App\Repository\PageRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension implements ResetInterface
{
    private const MONTHS = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];

    /** @var array<int, int>|null view counts keyed by article id, loaded once per request */
    private ?array $viewCounts = null;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly ArticleViewRepository $articleViewRepository,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('time_ago', [$this, 'timeAgo']),
            new TwigFilter('reading_time', [$this, 'readingTime']),
            new TwigFilter('project_progress_label', [$this, 'projectProgressLabel']),
            new TwigFilter('project_type_label', [$this, 'projectTypeLabel']),
            new TwigFilter('excerpt', [$this, 'excerpt']),
            new TwigFilter('french_date', [$this, 'frenchDate']),
            new TwigFilter('french_date_short', [$this, 'frenchDateShort']),
            new TwigFilter('format_number', [$this, 'formatNumber']),
            new TwigFilter('media_license_label', [$this, 'mediaLicenseLabel']),
            new TwigFilter('home_section_label', [$this, 'homeSectionLabel']),
        ];
    }

    public function mediaLicenseLabel(?MediaLicense $license): string
    {
        return match ($license) {
            MediaLicense::AllRightsReserved => 'Tous droits réservés',
            MediaLicense::CcBy => 'CC BY',
            MediaLicense::CcBySa => 'CC BY-SA',
            MediaLicense::CcByNc => 'CC BY-NC',
            MediaLicense::Cc0 => 'CC0 / Domaine public',
            MediaLicense::Internal => 'Usage interne (rédaction)',
            null => '—',
        };
    }

    public function homeSectionLabel(HomeSectionType $type): string
    {
        return match ($type) {
            HomeSectionType::HeroSlider => 'Slider principal',
            HomeSectionType::LatestNews => 'Dernières actualités',
            HomeSectionType::PopularArticles => 'Articles populaires',
            HomeSectionType::Videos => 'Vidéos',
            HomeSectionType::Podcasts => 'Podcasts',
            HomeSectionType::CategoryHighlight => 'Rubrique en avant',
            HomeSectionType::Contact => 'Nous joindre',
            HomeSectionType::Announcements => 'Annonces & Opportunités',
        };
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('static_pages', [$this, 'staticPages']),
            new TwigFunction('site_settings', [$this, 'siteSettings']),
            new TwigFunction('article_views', [$this, 'articleViews']),
        ];
    }

    /**
     * Published static pages, for the footer's page list — keeps the
     * footer partial self-sufficient regardless of which controller
     * includes it, same reasoning as the old flash-news band had.
     *
     * @return list<Page>
     */
    public function staticPages(): array
    {
        return $this->pageRepository->findPublished();
    }

    /**
     * The single global settings row (site name, logo, favicon, footer,
     * contacts, social links, maintenance) — lets any template call
     * site_settings() directly instead of every controller having to pass
     * it in explicitly, same reasoning as staticPages().
     */
    public function siteSettings(): SiteSettings
    {
        return $this->siteSettingsRepository->getSettings();
    }

    public function formatNumber(int $number): string
    {
        return number_format($number, 0, ',', ' ');
    }

    public function excerpt(?string $text, int $length = 140): string
    {
        // Espace avant chaque balise pour ne pas coller les paragraphes
        // (« fin.</p><p>Début »), puis décodage des entités de l'éditeur
        // riche (&nbsp;, &amp;…) et espaces normalisés.
        $text = strip_tags(str_replace('<', ' <', (string) $text));
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ('' === $text) {
            return '';
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length)).'…';
    }

    public function frenchDate(\DateTimeInterface $date): string
    {
        $days = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

        return ucfirst($days[(int) $date->format('N')]).' '.$this->frenchDateShort($date);
    }

    /**
     * « 4 février 2026 » — same as french_date without the weekday, used
     * on article cards.
     */
    public function frenchDateShort(\DateTimeInterface $date): string
    {
        return sprintf('%d %s %d', (int) $date->format('j'), self::MONTHS[(int) $date->format('n')], (int) $date->format('Y'));
    }

    /**
     * Total views of an article, for article cards. The whole id => count
     * map is loaded with a single GROUP BY on first use, so a page listing
     * many cards costs one query rather than one per card; reset() clears
     * it between requests (FrankenPHP worker mode keeps services alive).
     */
    public function articleViews(Article $article): int
    {
        $this->viewCounts ??= $this->articleViewRepository->countByArticleMap();

        return $this->viewCounts[$article->getId()] ?? 0;
    }

    public function reset(): void
    {
        $this->viewCounts = null;
    }

    public function timeAgo(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '';
        }

        $diff = (new \DateTimeImmutable())->getTimestamp() - $date->getTimestamp();

        if ($diff < 60) {
            return "à l'instant";
        }
        if ($diff < 3600) {
            return sprintf('il y a %d min', intdiv($diff, 60));
        }
        if ($diff < 86400) {
            return sprintf('il y a %d h', intdiv($diff, 3600));
        }
        if ($diff < 604800) {
            return sprintf('il y a %d j', intdiv($diff, 86400));
        }
        if ($diff < 2629800) {
            return sprintf('il y a %d sem', intdiv($diff, 604800));
        }

        return sprintf('il y a %d mois', intdiv($diff, 2629800));
    }

    public function readingTime(?string $content): int
    {
        if (!$content) {
            return 1;
        }

        $words = str_word_count(strip_tags($content));

        return max(1, (int) ceil($words / 200));
    }

    public function projectProgressLabel(ProjectProgress $progress): string
    {
        return match ($progress) {
            ProjectProgress::Planifie => 'Planifié',
            ProjectProgress::EnCours => 'En cours',
            ProjectProgress::Termine => 'Terminé',
            ProjectProgress::Suspendu => 'Suspendu',
            ProjectProgress::Abandonne => 'Abandonné',
        };
    }

    public function projectTypeLabel(ProjectType $type): string
    {
        return match ($type) {
            ProjectType::Public => 'Projet public',
            ProjectType::Prive => 'Projet privé',
        };
    }
}
