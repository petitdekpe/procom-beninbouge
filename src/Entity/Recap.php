<?php

namespace App\Entity;

use App\Entity\Enum\ArticleStatus;
use App\Repository\RecapRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Un « Récap » : une vidéo au format mobile (vertical 9:16) accompagnée d'un
 * court texte. Contenu à part entière, distinct d'Article : sa propre
 * table, son propre espace d'admin (/admin/recaps) et sa propre page
 * publique (/recap/{slug}, vidéo à gauche et texte à droite sur ordinateur).
 * Il alimente la section « Récap » de l'accueil. Pas de workflow éditorial,
 * de commentaires ni de statistiques de lecture : seulement brouillon ou
 * publié.
 */
#[ORM\Entity(repositoryClass: RecapRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ORM\HasLifecycleCallbacks]
#[Assert\Callback(callback: 'validateVideo')]
class Recap
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

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $content = null;

    /**
     * Lien YouTube (watch, youtu.be, shorts, embed) ou fichier vidéo direct
     * (.mp4/.webm), y compris un fichier téléversé (/uploads/videos/…, voir
     * App\Service\VideoUploader). Obligatoire, mais contrôlé par le
     * contrôleur d'admin plutôt que par une contrainte : un fichier
     * téléversé ne donne son URL qu'après la validation du formulaire.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $videoUrl = null;

    /**
     * Visuel des cartes et affiche du lecteur ; à défaut, la miniature
     * YouTube est utilisée (voir getThumbnail()).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(length: 70, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $metaDescription = null;

    /**
     * Seuls Draft et Published sont proposés dans l'admin.
     */
    #[ORM\Column(length: 20, enumType: ArticleStatus::class)]
    private ArticleStatus $status = ArticleStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Author $author = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function validateVideo(ExecutionContextInterface $context): void
    {
        if (null !== $this->videoUrl && '' !== $this->videoUrl && !$this->hasVideo()) {
            $context->buildViolation('La vidéo doit être un lien YouTube (vidéo ou Short) ou un fichier .mp4 / .webm.')
                ->atPath('videoUrl')
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

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

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

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): static
    {
        $videoUrl = null === $videoUrl ? null : trim($videoUrl);
        $this->videoUrl = '' === $videoUrl ? null : $videoUrl;

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

    public function isPublished(): bool
    {
        return ArticleStatus::Published === $this->status;
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

    /**
     * True when videoUrl is something the public page knows how to play.
     */
    public function hasVideo(): bool
    {
        return null !== $this->getVideoYoutubeId() || null !== $this->getVideoFileUrl();
    }

    public function getVideoYoutubeId(): ?string
    {
        if (!$this->videoUrl) {
            return null;
        }

        if (1 === preg_match('#^https?://(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/|watch\?(?:[^\#]*&)?v=)|youtu\.be/)([A-Za-z0-9_-]{6,15})#i', $this->videoUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Direct video file (uploaded, or an external .mp4/.webm link).
     */
    public function getVideoFileUrl(): ?string
    {
        if ($this->videoUrl && 1 === preg_match('#^(?:https?://|/uploads/videos/)[^\s"\'<>]+\.(?:mp4|webm|m4v|mov)(?:\?[^\s"\'<>]*)?$#i', $this->videoUrl)) {
            return $this->videoUrl;
        }

        return null;
    }

    public function getVideoEmbedSrc(): ?string
    {
        $id = $this->getVideoYoutubeId();

        return $id ? "https://www.youtube.com/embed/{$id}?rel=0&playsinline=1&modestbranding=1" : null;
    }

    /**
     * Visuel de la carte / affiche du lecteur : la couverture si elle existe,
     * sinon la miniature YouTube.
     */
    public function getThumbnail(): ?string
    {
        if ($this->coverImage) {
            return $this->coverImage;
        }

        $id = $this->getVideoYoutubeId();

        return $id ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : null;
    }

    /**
     * schema.org "VideoObject" structured data, ready to be embedded as JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function getStructuredData(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $this->title,
            'description' => $this->metaDescription ?: $this->title,
            'thumbnailUrl' => $this->getThumbnail(),
            'uploadDate' => $this->publishedAt?->format(\DateTimeInterface::ATOM),
            'embedUrl' => $this->getVideoEmbedSrc(),
            'contentUrl' => $this->getVideoFileUrl(),
        ];
    }
}
