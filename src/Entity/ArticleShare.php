<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\ArticleShareRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A click on one of an article's share buttons (Facebook/X/WhatsApp/
 * LinkedIn/copy-link) — see assets/controllers/share_tracker_controller.js,
 * which fires this the moment the button is clicked (not after a real
 * share completes, which the browser never reports back — same
 * "best-effort" reasoning as every other click-based metric here).
 * Same public-write reasoning as ArticleView.
 */
#[ORM\Entity(repositoryClass: ArticleShareRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(),
    ],
)]
class ArticleShare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Article $article = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['facebook', 'twitter', 'whatsapp', 'linkedin', 'copy_link'])]
    private ?string $channel = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $visitorId = null;

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

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
