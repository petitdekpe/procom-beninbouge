<?php

namespace App\Entity;

use App\Entity\Enum\ArticleStatus;
use App\Repository\ArticleRevisionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A full snapshot of an Article's editable fields, taken every time it's
 * saved (see App\Service\ArticleSaver::afterSave(), the single hook point
 * shared by the backoffice and the API, and
 * App\Command\PublishScheduledArticlesCommand for the automatic
 * Scheduled → Published transition) — append-only, never edited or
 * deleted except alongside the article itself.
 *
 * categoryName is a denormalized snapshot rather than a live reference to
 * Category: a revision must keep reading correctly even if that category
 * is later renamed or deleted.
 */
#[ORM\Entity(repositoryClass: ArticleRevisionRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
class ArticleRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $chapo = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $categoryName = null;

    #[ORM\Column(length: 20, enumType: ArticleStatus::class)]
    private ?ArticleStatus $status = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $editedBy = null;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCategoryName(): ?string
    {
        return $this->categoryName;
    }

    public function setCategoryName(?string $categoryName): static
    {
        $this->categoryName = $categoryName;

        return $this;
    }

    public function getStatus(): ?ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getEditedBy(): ?User
    {
        return $this->editedBy;
    }

    public function setEditedBy(?User $editedBy): static
    {
        $this->editedBy = $editedBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
