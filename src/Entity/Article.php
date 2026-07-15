<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Doctrine\ArticleSearchFilter;
use App\Doctrine\SoftDeletableInterface;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Enum\ArticleType;
use App\Repository\ArticleRepository;
use App\State\ArticleDeleteProcessor;
use App\State\ArticleWriteProcessor;
use App\State\CachedArticleCollectionProvider;
use App\State\RelatedArticlesProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: CachedArticleCollectionProvider::class,
            cacheHeaders: ['max_age' => 60, 'shared_max_age' => 3600, 'vary' => ['Authorization']],
        ),
        new Get(
            cacheHeaders: ['max_age' => 60, 'shared_max_age' => 3600, 'vary' => ['Authorization']],
        ),
        new GetCollection(
            uriTemplate: '/articles/{id}/related',
            uriVariables: ['id' => new Link(fromClass: self::class, identifiers: ['id'])],
            provider: RelatedArticlesProvider::class,
            cacheHeaders: ['max_age' => 60, 'shared_max_age' => 3600, 'vary' => ['Authorization']],
        ),
        new Post(security: "is_granted('ROLE_EDITOR')", processor: ArticleWriteProcessor::class),
        new Put(security: "is_granted('ROLE_EDITOR')", processor: ArticleWriteProcessor::class),
        new Patch(security: "is_granted('ROLE_EDITOR')", processor: ArticleWriteProcessor::class),
        new Delete(security: "is_granted('ROLE_EDITOR')", processor: ArticleDeleteProcessor::class),
    ],
    paginationItemsPerPage: 10,
    paginationMaximumItemsPerPage: 50,
    paginationClientItemsPerPage: true,
)]
#[ApiFilter(ArticleSearchFilter::class)]
#[ApiFilter(SearchFilter::class, properties: ['category' => 'exact', 'tags' => 'exact', 'status' => 'exact', 'type' => 'exact'])]
#[Assert\Callback(callback: 'validateSponsorship')]
class Article implements SoftDeletableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 280, unique: true)]
    #[Assert\Length(max: 280)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $chapo = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    /**
     * Generated automatically from the title when left blank.
     */
    #[ORM\Column(length: 70, nullable: true)]
    private ?string $metaTitle = null;

    /**
     * Generated automatically from the chapo/content when left blank.
     */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 20, enumType: ArticleStatus::class)]
    private ArticleStatus $status = ArticleStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Soft-delete marker (see App\Doctrine\SoftDeleteFilter): set when the
     * article is moved to the trash, cleared on restore. Non-null means
     * the article is invisible everywhere except the trash listing, which
     * explicitly disables the "soft_delete" filter to see it.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column]
    private bool $pinned = false;

    /**
     * Marque les publi-reportages (contenu sponsorisé) afin qu'ils
     * restent clairement identifiables côté API/frontend, distincts du
     * contenu éditorial classique.
     */
    #[ORM\Column(length: 20, enumType: ArticleType::class)]
    private ArticleType $type = ArticleType::Editorial;

    #[ORM\ManyToOne(targetEntity: Partner::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Partner $sponsor = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Author $author = null;

    /**
     * True while an editor has attributed this article to an author other
     * than themselves and asked for it to go live: it is held back as a
     * draft (see App\Service\ArticleSaver::applyAuthorshipGate) until an
     * admin approves it via App\Controller\Admin\ValidationController.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column]
    private bool $pendingApproval = false;

    /**
     * The status the editor actually asked for while pendingApproval was
     * triggered (status itself is forced back to Draft in the meantime),
     * so approval can put the article exactly where they intended.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 20, nullable: true, enumType: ArticleStatus::class)]
    private ?ArticleStatus $requestedStatus = null;

    /**
     * Set by the rédacteur en chef (Relecture) or the correcteur
     * (Correction) when they send an article back to the journalist —
     * see App\Service\ArticleSaver::rejectReview()/rejectCorrection().
     * Cleared automatically the next time it's resubmitted.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ApiProperty(readable: false, writable: false)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $approvedBy = null;

    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'article_tag', options: ['engine' => 'InnoDB'])]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function validateSponsorship(ExecutionContextInterface $context): void
    {
        if (ArticleType::Sponsored === $this->type && !$this->sponsor) {
            $context->buildViolation("Un publi-reportage (type sponsored) doit référencer un annonceur (sponsor).")
                ->atPath('sponsor')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function setPinned(bool $pinned): static
    {
        $this->pinned = $pinned;

        return $this;
    }

    public function getType(): ArticleType
    {
        return $this->type;
    }

    public function setType(ArticleType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isSponsored(): bool
    {
        return ArticleType::Sponsored === $this->type;
    }

    public function getSponsor(): ?Partner
    {
        return $this->sponsor;
    }

    public function setSponsor(?Partner $sponsor): static
    {
        $this->sponsor = $sponsor;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function isPendingApproval(): bool
    {
        return $this->pendingApproval;
    }

    public function setPendingApproval(bool $pendingApproval): static
    {
        $this->pendingApproval = $pendingApproval;

        return $this;
    }

    public function getRequestedStatus(): ?ArticleStatus
    {
        return $this->requestedStatus;
    }

    public function setRequestedStatus(?ArticleStatus $requestedStatus): static
    {
        $this->requestedStatus = $requestedStatus;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * schema.org "Article" structured data, ready to be embedded as JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function getStructuredData(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $this->title,
            'description' => $this->chapo,
            'image' => $this->coverImage,
            'datePublished' => $this->publishedAt?->format(\DateTimeInterface::ATOM),
            'dateModified' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
            'articleSection' => $this->category?->getName(),
            'author' => $this->author ? [
                '@type' => 'Person',
                'name' => trim($this->author->getFirstName().' '.$this->author->getLastName()),
            ] : null,
            // Disclosure schema.org pour les publi-reportages : un client
            // (Google, agrégateurs, etc.) peut détecter le sponsoring même
            // sans regarder le champ "type" hors structured data.
            'isAccessibleForFree' => !$this->isSponsored(),
            'sponsor' => $this->isSponsored() && $this->sponsor ? [
                '@type' => 'Organization',
                'name' => $this->sponsor->getName(),
            ] : null,
        ];
    }
}
