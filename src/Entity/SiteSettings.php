<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Global site configuration — always exactly one row, read via
 * App\Repository\SiteSettingsRepository::getSettings() (which creates it
 * on first access) and edited via App\Controller\Admin\SiteSettingsController.
 * Exposed to every public template through the site_settings() Twig
 * function (App\Twig\AppExtension), the same pattern static_pages() uses.
 */
#[ORM\Entity(repositoryClass: \App\Repository\SiteSettingsRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
class SiteSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $siteName = 'Bénin Bouge';

    // ----- Logo / favicon -----

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoUrl = null;

    /**
     * Variant shown on the dark footer background — kept distinct from
     * logoUrl because a single logo file rarely reads well on both a
     * light header and a dark footer.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoFooterUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $faviconUrl = null;

    // ----- Pied de page -----

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $footerTagline = null;

    // ----- Contacts -----

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactAddress = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $contactEmail = null;

    // ----- Emails (adresses utilisées par de futurs envois automatiques) -----

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $emailFromName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $emailFromAddress = null;

    // ----- Réseaux sociaux -----

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $facebookUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $twitterUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $instagramUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $youtubeUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $whatsappUrl = null;

    // ----- Maintenance -----

    #[ORM\Column]
    private bool $maintenanceMode = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $maintenanceMessage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteName(): string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): static
    {
        $this->siteName = $siteName;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;

        return $this;
    }

    public function getLogoFooterUrl(): ?string
    {
        return $this->logoFooterUrl;
    }

    public function setLogoFooterUrl(?string $logoFooterUrl): static
    {
        $this->logoFooterUrl = $logoFooterUrl;

        return $this;
    }

    public function getFaviconUrl(): ?string
    {
        return $this->faviconUrl;
    }

    public function setFaviconUrl(?string $faviconUrl): static
    {
        $this->faviconUrl = $faviconUrl;

        return $this;
    }

    public function getFooterTagline(): ?string
    {
        return $this->footerTagline;
    }

    public function setFooterTagline(?string $footerTagline): static
    {
        $this->footerTagline = $footerTagline;

        return $this;
    }

    public function getContactAddress(): ?string
    {
        return $this->contactAddress;
    }

    public function setContactAddress(?string $contactAddress): static
    {
        $this->contactAddress = $contactAddress;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getEmailFromName(): ?string
    {
        return $this->emailFromName;
    }

    public function setEmailFromName(?string $emailFromName): static
    {
        $this->emailFromName = $emailFromName;

        return $this;
    }

    public function getEmailFromAddress(): ?string
    {
        return $this->emailFromAddress;
    }

    public function setEmailFromAddress(?string $emailFromAddress): static
    {
        $this->emailFromAddress = $emailFromAddress;

        return $this;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function setFacebookUrl(?string $facebookUrl): static
    {
        $this->facebookUrl = $facebookUrl;

        return $this;
    }

    public function getTwitterUrl(): ?string
    {
        return $this->twitterUrl;
    }

    public function setTwitterUrl(?string $twitterUrl): static
    {
        $this->twitterUrl = $twitterUrl;

        return $this;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function setInstagramUrl(?string $instagramUrl): static
    {
        $this->instagramUrl = $instagramUrl;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        return $this;
    }

    public function getWhatsappUrl(): ?string
    {
        return $this->whatsappUrl;
    }

    public function setWhatsappUrl(?string $whatsappUrl): static
    {
        $this->whatsappUrl = $whatsappUrl;

        return $this;
    }

    public function isMaintenanceMode(): bool
    {
        return $this->maintenanceMode;
    }

    public function setMaintenanceMode(bool $maintenanceMode): static
    {
        $this->maintenanceMode = $maintenanceMode;

        return $this;
    }

    public function getMaintenanceMessage(): ?string
    {
        return $this->maintenanceMessage;
    }

    public function setMaintenanceMessage(?string $maintenanceMessage): static
    {
        $this->maintenanceMessage = $maintenanceMessage;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
