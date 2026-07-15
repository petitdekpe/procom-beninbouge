<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Enum\CommentStatus;
use App\Repository\CommentRepository;
use App\State\CommentCreateProcessor;
use App\State\CommentDeleteProcessor;
use App\State\CommentModerateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Anonymous — this site has no public reader account system (staff-only
 * login via ROLE_EDITOR/ROLE_ADMIN), so a commenter is only ever a
 * freeform name (+ optional email, never displayed), not a User. Every
 * comment starts Pending and is only shown publicly once an editor
 * approves it — see App\Controller\CommentController (public submission)
 * and App\Controller\Admin\CommentController (moderation).
 */
#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            denormalizationContext: ['groups' => ['comment:create']],
            processor: CommentCreateProcessor::class,
        ),
        new Patch(
            uriTemplate: '/comments/{id}/moderate',
            security: "is_granted('ROLE_EDITOR')",
            denormalizationContext: ['groups' => ['comment:moderate']],
            processor: CommentModerateProcessor::class,
        ),
        new Delete(security: "is_granted('ROLE_EDITOR')", processor: CommentDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['comment:read']],
)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comment:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['comment:read', 'comment:create'])]
    private ?string $content = null;

    #[ORM\Column(length: 20, enumType: CommentStatus::class)]
    #[Groups(['comment:read', 'comment:moderate'])]
    private CommentStatus $status = CommentStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['comment:read', 'comment:create'])]
    private ?Article $article = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['comment:read', 'comment:create'])]
    private ?string $authorName = null;

    /**
     * Never displayed publicly — collected only so moderation can follow
     * up if needed.
     */
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Groups(['comment:create'])]
    private ?string $authorEmail = null;

    #[ORM\Column]
    #[Groups(['comment:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStatus(): CommentStatus
    {
        return $this->status;
    }

    public function setStatus(CommentStatus $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): static
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
