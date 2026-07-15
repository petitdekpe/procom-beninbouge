<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\ArticleImpressionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One article card rendered inside a listing (homepage section, rubrique
 * page, mot-clé page, dossier) — see
 * assets/controllers/impression_tracker_controller.js, which fires one of
 * these per visible card when the listing page loads. Paired against
 * ArticleView to approximate a click-through rate: this codebase has no
 * true "shown vs. clicked" attribution (no ad-serving infra), so CTR here
 * is simply views / impressions per article — a best-effort proxy, not a
 * rigorously attributed metric, and labelled as such wherever it's shown.
 * Unlike ArticleView/ArticleShare, no visitorId: de-duplicating
 * impressions isn't worth the complexity for this proxy metric.
 */
#[ORM\Entity(repositoryClass: ArticleImpressionRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(),
    ],
)]
class ArticleImpression
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Article $article = null;

    /**
     * Where the card was shown (e.g. "home_latest_news", "category_page",
     * "tag_page", "dossier_page") — informational only, not required for
     * the CTR calculation itself.
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $context = null;

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

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
