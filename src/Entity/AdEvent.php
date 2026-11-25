<?php

namespace App\Entity;

use App\Entity\Enum\AdEventKind;
use App\Repository\AdEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un affichage ou un clic sur un contenu de la régie : une publicité du
 * slider de l'accueil (HomeBanner) ou une annonce payante (Announcement).
 * Enregistré par assets/controllers/ad_tracker_controller.js via
 * App\Controller\AdTrackingController, lu par la page admin « Performance »
 * (App\Controller\Admin\RegieStatsController).
 *
 * Exactement l'une des deux cibles est renseignée. « placement » dit où le
 * contenu a été vu : home_slider (publicité), home_card, popup, list ou
 * detail (annonce ; detail = page de l'annonce, donc « vue » et clic sur un
 * lien de contact). Pas de visiteur identifié : visitorId est le cookie
 * anonyme déjà utilisé pour les lecteurs (assets/visitor.js), nul s'il est
 * refusé.
 */
#[ORM\Entity(repositoryClass: AdEventRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ORM\Index(name: 'idx_ad_event_banner', columns: ['banner_id', 'kind', 'created_at'])]
#[ORM\Index(name: 'idx_ad_event_announcement', columns: ['announcement_id', 'kind', 'created_at'])]
class AdEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomeBanner::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?HomeBanner $banner = null;

    #[ORM\ManyToOne(targetEntity: Announcement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Announcement $announcement = null;

    #[ORM\Column(length: 20, enumType: AdEventKind::class)]
    private AdEventKind $kind;

    #[ORM\Column(length: 30)]
    private string $placement;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $visitorId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(AdEventKind $kind, string $placement, ?string $visitorId = null)
    {
        $this->kind = $kind;
        $this->placement = $placement;
        $this->visitorId = $visitorId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBanner(): ?HomeBanner
    {
        return $this->banner;
    }

    public function setBanner(?HomeBanner $banner): static
    {
        $this->banner = $banner;

        return $this;
    }

    public function getAnnouncement(): ?Announcement
    {
        return $this->announcement;
    }

    public function setAnnouncement(?Announcement $announcement): static
    {
        $this->announcement = $announcement;

        return $this;
    }

    public function getKind(): AdEventKind
    {
        return $this->kind;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitorId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
