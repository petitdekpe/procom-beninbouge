<?php

namespace App\Entity;

use App\Repository\MobileRequestKeyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Identifiant choisi par le téléphone pour une création (article, publicité,
 * annonce). Si la réponse se perd à cause du réseau et que l'application
 * renvoie la même demande, le contenu n'est pas créé une seconde fois.
 */
#[ORM\Entity(repositoryClass: MobileRequestKeyRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'uniq_mobile_request_key', columns: ['user_id', 'client_id'])]
class MobileRequestKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64)]
    private string $clientId;

    #[ORM\Column(length: 20)]
    private string $targetType;

    #[ORM\Column]
    private int $targetId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $clientId, string $targetType, int $targetId)
    {
        $this->user = $user;
        $this->clientId = $clientId;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }
}
