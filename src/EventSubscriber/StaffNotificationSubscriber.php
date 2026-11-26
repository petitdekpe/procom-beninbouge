<?php

namespace App\EventSubscriber;

use App\Entity\Announcement;
use App\Entity\Article;
use App\Entity\Enum\AnnouncementStatus;
use App\Entity\Enum\ArticleStatus;
use App\Entity\User;
use App\Repository\MobileTokenRepository;
use App\Security\PermissionChecker;
use App\Service\Push\FcmClient;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Prévient l'équipe sur son téléphone quand un contenu change d'étape. On
 * observe les changements de statut enregistrés en base : le back-office,
 * l'application mobile, le paiement en ligne des annonces et les commandes
 * sont ainsi couverts sans rien ajouter à chacun. L'envoi attend la fin de
 * la requête.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class StaffNotificationSubscriber implements EventSubscriberInterface, ResetInterface
{
    /** @var list<array{object, string}> changements en cours d'enregistrement */
    private array $staged = [];

    /** @var list<array{object, string}> changements enregistrés, à annoncer */
    private array $ready = [];

    public function __construct(
        private FcmClient $fcm,
        private MobileTokenRepository $tokenRepository,
        private PermissionChecker $permissionChecker,
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'deliver',
            ConsoleEvents::TERMINATE => 'deliver',
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->staged = [];
        if (!$this->fcm->isConfigured()) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Article && !$entity instanceof Announcement) {
                continue;
            }
            $change = $unitOfWork->getEntityChangeSet($entity)['status'] ?? null;
            if (null === $change) {
                continue;
            }

            [$old, $new] = array_map(static fn ($s) => $s instanceof \BackedEnum ? $s->value : $s, $change);
            if ($old === $new) {
                continue;
            }
            $event = $entity instanceof Article ? $this->articleEvent($entity, (string) $old, (string) $new) : (AnnouncementStatus::PendingReview->value === $new ? 'announcement_pending' : null);
            if (null !== $event) {
                $this->staged[] = [$entity, $event];
            }
        }
    }

    public function postFlush(): void
    {
        array_push($this->ready, ...$this->staged);
        $this->staged = [];
    }

    public function deliver(): void
    {
        if ([] === $this->ready) {
            return;
        }
        $events = $this->ready;
        $this->ready = [];

        $actor = $this->security->getUser();
        $tokens = array_filter($this->tokenRepository->findWithPushToken(), static fn ($t) => $t->isUsable());
        $dirty = false;

        foreach ($events as [$entity, $event]) {
            [$permission, $title] = match ($event) {
                'review' => ['workflow.review', 'Article à relire'],
                'correction' => ['workflow.proofread', 'Article à corriger'],
                'returned' => [null, 'Votre article vous est renvoyé'],
                'published' => [null, 'Votre article est publié'],
                'scheduled' => [null, 'Votre article est programmé'],
                'announcement_pending' => ['announcements.manage', 'Annonce à valider'],
            };
            $owner = $entity instanceof Article ? $entity->getAuthor()?->getUser() : null;

            foreach ($tokens as $token) {
                $user = $token->getUser();
                $concerned = null === $permission
                    ? $owner instanceof User && $user === $owner
                    : $this->permissionChecker->has($user, $permission);
                if (!$concerned || $user === $actor || null === $token->getPushToken()) {
                    continue;
                }

                $delivered = $this->fcm->send($token->getPushToken(), $title, (string) $entity->getTitle(), [
                    'type' => $entity instanceof Article ? 'article' : 'announcement',
                    'id' => (string) $entity->getId(),
                ]);
                if (!$delivered) {
                    $token->setPushToken(null);
                    $dirty = true;
                }
            }
        }

        if ($dirty) {
            $this->entityManager->flush();
        }
    }

    public function reset(): void
    {
        $this->staged = [];
        $this->ready = [];
    }

    private function articleEvent(Article $article, string $old, string $new): ?string
    {
        $inCircuit = [ArticleStatus::InReview->value, ArticleStatus::InCorrection->value];

        return match (true) {
            ArticleStatus::InReview->value === $new => 'review',
            ArticleStatus::InCorrection->value === $new => 'correction',
            ArticleStatus::Draft->value === $new && \in_array($old, $inCircuit, true) && null !== $article->getRejectionReason() => 'returned',
            ArticleStatus::Published->value === $new && \in_array($old, [...$inCircuit, ArticleStatus::Scheduled->value], true) => 'published',
            ArticleStatus::Scheduled->value === $new && \in_array($old, $inCircuit, true) => 'scheduled',
            default => null,
        };
    }
}
