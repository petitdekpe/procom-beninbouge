<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Enum\MediaLicense;
use App\Entity\Enum\MediaType;
use App\Repository\MediaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_EDITOR')"),
        new Put(security: "is_granted('ROLE_EDITOR')"),
        new Patch(security: "is_granted('ROLE_EDITOR')"),
        new Delete(security: "is_granted('ROLE_EDITOR')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'article' => 'exact', 'gallery' => 'exact'])]
#[Assert\Callback(callback: 'validateTypeSpecificFields')]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: MediaType::class)]
    #[Assert\NotNull]
    private ?MediaType $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Author $author = null;

    #[ORM\Column(length: 20, nullable: true, enumType: MediaLicense::class)]
    private ?MediaLicense $license = null;

    /**
     * A media can belong to several rubriques at once (e.g. a video about
     * an election result could be both "Politique" and "International").
     *
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'media_category', options: ['engine' => 'InnoDB'])]
    private Collection $categories;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'media_tag', options: ['engine' => 'InnoDB'])]
    private Collection $tags;

    #[ORM\Column]
    private int $position = 0;

    /**
     * Nullable so that videos imported automatically from the YouTube
     * channel (App\Service\YoutubeChannelSyncService) don't need to be
     * attached to an article. Images and audio still require one — see
     * validateTypeSpecificFields().
     */
    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Gallery::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Gallery $gallery = null;

    // Champs spécifiques aux images
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    // Champs spécifiques aux vidéos
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeEmbedUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    /**
     * The YouTube video ID (e.g. "dQw4w9WgXcQ"), set only for videos
     * imported automatically from the channel feed. Used to avoid
     * re-importing the same video on every sync run.
     */
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $youtubeVideoId = null;

    // Champs spécifiques à l'audio / podcast
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $audioUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $transcript = null;

    // Champs spécifiques aux documents PDF
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentUrl = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function validateTypeSpecificFields(ExecutionContextInterface $context): void
    {
        if (\in_array($this->type, [MediaType::Image, MediaType::Infographic], true) && !$this->imageUrl) {
            $context->buildViolation("Une image ou une infographie doit avoir une URL ou un chemin de fichier (imageUrl).")
                ->atPath('imageUrl')
                ->addViolation();
        }

        if (MediaType::Video === $this->type && !$this->youtubeEmbedUrl) {
            $context->buildViolation("Une vidéo doit avoir une URL d'embed YouTube (youtubeEmbedUrl).")
                ->atPath('youtubeEmbedUrl')
                ->addViolation();
        }

        if (MediaType::Audio === $this->type && !$this->audioUrl) {
            $context->buildViolation("Un podcast doit avoir un fichier ou une URL audio (audioUrl).")
                ->atPath('audioUrl')
                ->addViolation();
        }

        if (MediaType::Pdf === $this->type && !$this->documentUrl) {
            $context->buildViolation("Un document PDF doit avoir un fichier ou une URL (documentUrl).")
                ->atPath('documentUrl')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?MediaType
    {
        return $this->type;
    }

    public function setType(MediaType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getLicense(): ?MediaLicense
    {
        return $this->license;
    }

    public function setLicense(?MediaLicense $license): static
    {
        $this->license = $license;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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

    public function getGallery(): ?Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getYoutubeEmbedUrl(): ?string
    {
        return $this->youtubeEmbedUrl;
    }

    public function setYoutubeEmbedUrl(?string $youtubeEmbedUrl): static
    {
        $this->youtubeEmbedUrl = $youtubeEmbedUrl;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getYoutubeVideoId(): ?string
    {
        return $this->youtubeVideoId;
    }

    public function setYoutubeVideoId(?string $youtubeVideoId): static
    {
        $this->youtubeVideoId = $youtubeVideoId;

        return $this;
    }

    /**
     * The thumbnail actually shown for a video: the hand-set/uploaded one
     * if there is one, otherwise YouTube's own default thumbnail derived
     * from youtubeEmbedUrl — so a video never needs a thumbnail of its
     * own to display correctly. Returns null for non-video media or when
     * no usable embed URL is set.
     */
    public function getEffectiveThumbnail(): ?string
    {
        if ($this->thumbnail) {
            return $this->thumbnail;
        }

        if (MediaType::Video !== $this->type) {
            return null;
        }

        $videoId = $this->youtubeVideoId ?? $this->extractYoutubeVideoId();

        return $videoId ? "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg" : null;
    }

    /**
     * Extracts the 11-character video ID from common YouTube URL shapes
     * (embed, watch?v=, youtu.be share links) — youtubeEmbedUrl is meant
     * to hold an embed URL, but editors sometimes paste a different one.
     * Only a fallback for hand-entered videos: ones imported from the
     * channel feed already have youtubeVideoId stored directly.
     */
    private function extractYoutubeVideoId(): ?string
    {
        if (!$this->youtubeEmbedUrl) {
            return null;
        }

        if (1 === preg_match('#(?:youtube\.com/embed/|youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})#', $this->youtubeEmbedUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getAudioUrl(): ?string
    {
        return $this->audioUrl;
    }

    public function setAudioUrl(?string $audioUrl): static
    {
        $this->audioUrl = $audioUrl;

        return $this;
    }

    public function getTranscript(): ?string
    {
        return $this->transcript;
    }

    public function setTranscript(?string $transcript): static
    {
        $this->transcript = $transcript;

        return $this;
    }

    public function getDocumentUrl(): ?string
    {
        return $this->documentUrl;
    }

    public function setDocumentUrl(?string $documentUrl): static
    {
        $this->documentUrl = $documentUrl;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Only meant for backdating videos imported from YouTube to their
     * actual publish date (App\Service\YoutubeChannelSyncService), so they
     * sort correctly alongside hand-entered media.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * schema.org "VideoObject" structured data for video media, ready to
     * be embedded as JSON-LD. Returns null for non-video media.
     *
     * @return array<string, mixed>|null
     */
    public function getStructuredData(): ?array
    {
        if (MediaType::Video !== $this->type) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $this->title,
            'thumbnailUrl' => $this->getEffectiveThumbnail(),
            'embedUrl' => $this->youtubeEmbedUrl,
            'uploadDate' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
