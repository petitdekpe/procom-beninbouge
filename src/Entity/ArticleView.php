<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\ArticleViewRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une consultation d'article, journalisée pour nourrir l'endpoint
 * d'analytics (vues, sources, articles les plus performants). La création
 * reste publique : c'est le frontend qui notifie chaque affichage de page.
 */
#[ORM\Entity(repositoryClass: ArticleViewRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(),
    ],
)]
class ArticleView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Article $article = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $source = null;

    /**
     * Opaque anonymous identifier from a first-party cookie set by
     * assets/controllers/article_tracker_controller.js — lets
     * ArticleViewRepository tell a repeat visit from a genuinely new
     * reader apart (COUNT DISTINCT). Null when the visitor has cookies
     * blocked; those views simply don't contribute to the unique count.
     */
    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $visitorId = null;

    /**
     * Real time spent on the article page, in seconds, reported once via
     * sendBeacon when the tab is hidden/closed — unlike
     * AppExtension::readingTime() (a static word-count estimate), this is
     * actual telemetry. Null until that beacon fires (e.g. instant
     * bounces never send one).
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 3600)]
    private ?int $durationSeconds = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitorId;
    }

    public function setVisitorId(?string $visitorId): static
    {
        $this->visitorId = $visitorId;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
