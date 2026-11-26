<?php

namespace App\Entity;

use App\Repository\MobileTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Accès d'un téléphone à l'application mobile (App\Controller\Api\Mobile).
 * Seule l'empreinte SHA-256 du jeton est conservée. L'accès tombe de
 * lui-même après IDLE_DAYS jours sans usage, ou dès que le mot de passe du
 * compte change (passwordFingerprint) : réinitialiser le mot de passe d'une
 * personne coupe donc tous ses téléphones. Volontairement absent de l'API
 * Platform.
 */
#[ORM\Entity(repositoryClass: MobileTokenRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
class MobileToken
{
    public const IDLE_DAYS = 90;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(length: 64)]
    private string $passwordFingerprint;

    #[ORM\Column(length: 120)]
    private string $deviceName;

    /** Jeton Firebase du téléphone, pour les notifications. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pushToken = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastUsedAt;

    public function __construct(User $user, string $plainToken, string $deviceName)
    {
        $this->user = $user;
        $this->tokenHash = self::hash($plainToken);
        $this->passwordFingerprint = self::fingerprint($user);
        $this->deviceName = mb_substr($deviceName, 0, 120);
        $this->createdAt = new \DateTimeImmutable();
        $this->lastUsedAt = $this->createdAt;
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private static function fingerprint(User $user): string
    {
        return hash('sha256', (string) $user->getPassword());
    }

    public function isUsable(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->user->isEnabled()
            && !$this->user->isMustChangePassword()
            && hash_equals($this->passwordFingerprint, self::fingerprint($this->user))
            && $this->lastUsedAt > $now->modify(sprintf('-%d days', self::IDLE_DAYS));
    }

    /** Rafraîchit la date d'usage au plus toutes les 10 minutes. Vrai s'il faut enregistrer. */
    public function touch(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();
        if ($this->lastUsedAt > $now->modify('-10 minutes')) {
            return false;
        }
        $this->lastUsedAt = $now;

        return true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    public function getPushToken(): ?string
    {
        return $this->pushToken;
    }

    public function setPushToken(?string $pushToken): static
    {
        $this->pushToken = $pushToken;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): \DateTimeImmutable
    {
        return $this->lastUsedAt;
    }
}
