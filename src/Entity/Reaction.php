<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Enum\ReactionType;
use App\Repository\ReactionRepository;
use App\State\ReactionCreateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReactionRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'uniq_reaction_article_author', columns: ['article_id', 'author_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_READER')",
            denormalizationContext: ['groups' => ['reaction:create']],
            processor: ReactionCreateProcessor::class,
        ),
        new Delete(security: "is_granted('ROLE_EDITOR')"),
    ],
    normalizationContext: ['groups' => ['reaction:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'article' => 'exact'])]
class Reaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reaction:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: ReactionType::class)]
    #[Assert\NotNull]
    #[Groups(['reaction:read', 'reaction:create'])]
    private ?ReactionType $type = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['reaction:read', 'reaction:create'])]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['reaction:read'])]
    private ?User $author = null;

    #[ORM\Column]
    #[Groups(['reaction:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?ReactionType
    {
        return $this->type;
    }

    public function setType(ReactionType $type): static
    {
        $this->type = $type;

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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
