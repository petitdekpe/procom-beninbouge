<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleRevision;
use App\Entity\Enum\ActivityAction;
use App\Entity\Enum\ArticleStatus;
use App\Entity\User;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Shared Article write/delete side effects (SEO autofill, listing cache
 * invalidation, publication/deletion activity logging, revision
 * snapshotting), used both by the API Platform processors
 * (App\State\ArticleWriteProcessor / ArticleDeleteProcessor) and by the
 * backoffice (App\Controller\Admin\ArticleController) so the two entry
 * points never drift apart.
 */
final class ArticleSaver
{
    public function __construct(
        private SeoMetadataGenerator $seo,
        private ArticleRepository $articleRepository,
        private ActivityLogger $activityLogger,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.listings')]
        private CacheItemPoolInterface $listingsCache,
        private Security $security,
    ) {
    }

    /**
     * Fills in blank SEO fields and auto-stamps publishedAt. Returns
     * whether the article was already published *before* this change,
     * so the caller can detect a draft/scheduled → published transition
     * once the write actually lands (see afterSave()).
     */
    public function applyDefaults(Article $article): bool
    {
        $originalStatus = $this->entityManager->getUnitOfWork()->getOriginalEntityData($article)['status'] ?? null;
        $wasPublished = ArticleStatus::Published === $originalStatus;

        $this->applyAuthorshipGate($article);

        if (!$article->getSlug() && $article->getTitle()) {
            $article->setSlug($this->seo->uniqueSlug(
                $article->getTitle(),
                fn (string $candidate): bool => $this->slugTakenByAnotherArticle($candidate, $article),
            ));
        }

        if (!$article->getMetaTitle() && $article->getTitle()) {
            $article->setMetaTitle($this->seo->metaTitle($article->getTitle()));
        }

        if (!$article->getMetaDescription()) {
            $source = $article->getChapo() ?: $article->getContent();
            if ($source) {
                $article->setMetaDescription($this->seo->metaDescription($source));
            }
        }

        if (ArticleStatus::Published === $article->getStatus() && !$article->getPublishedAt()) {
            $article->setPublishedAt(new \DateTimeImmutable());
        }

        return $wasPublished;
    }

    /**
     * Call once the write has actually been flushed (by whichever path
     * did the flushing): invalidates the listing cache, logs a
     * publication event when the article just became published, and
     * snapshots a revision — the single hook point shared by every save
     * path (backoffice, API, the scheduled-publish command) is what
     * makes "Historique des modifications" a complete record instead of
     * one more thing every caller has to remember to do.
     */
    public function afterSave(Article $article, bool $wasPublished): void
    {
        $this->listingsCache->clear();

        if (ArticleStatus::Published === $article->getStatus() && !$wasPublished) {
            $this->activityLogger->log(ActivityAction::Publication, 'Article', $article->getId());
        }

        $this->snapshotRevision($article);
    }

    private function snapshotRevision(Article $article): void
    {
        $revision = (new ArticleRevision())
            ->setArticle($article)
            ->setTitle($article->getTitle())
            ->setChapo($article->getChapo())
            ->setContent($article->getContent())
            ->setCategoryName($article->getCategory()?->getName())
            ->setStatus($article->getStatus());

        $actor = $this->security->getUser();
        if ($actor instanceof User) {
            $revision->setEditedBy($actor);
        }

        $this->entityManager->persist($revision);
        $this->entityManager->flush();
    }

    /**
     * Full persist+flush for callers that don't already go through API
     * Platform's persist_processor (i.e. the backoffice): call
     * applyDefaults() first, then pass its result in here.
     */
    public function save(Article $article, bool $wasPublished): void
    {
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        $this->afterSave($article, $wasPublished);
    }

    /**
     * Brings back an old revision's title/chapo/content — deliberately
     * *not* its status or category: restoring a draft's wording shouldn't
     * silently unpublish a now-live article. Goes through the normal
     * afterSave() flow, so the restore itself becomes a new revision —
     * history is append-only, nothing is ever lost.
     */
    public function restoreRevision(Article $article, ArticleRevision $revision): void
    {
        $article->setTitle($revision->getTitle());
        $article->setChapo($revision->getChapo());
        $article->setContent($revision->getContent());

        $wasPublished = ArticleStatus::Published === $article->getStatus();
        $this->entityManager->flush();
        $this->afterSave($article, $wasPublished);
    }

    /**
     * Journaliste : Création → Relecture. Clears any earlier rejection
     * reason — this is a fresh attempt, not a continuation of the old one.
     */
    public function submitForReview(Article $article): void
    {
        $article->setStatus(ArticleStatus::InReview);
        $article->setRejectionReason(null);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::SubmittedForReview, 'Article', $article->getId());
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Rédacteur en chef : Relecture → Correction.
     */
    public function approveReview(Article $article): void
    {
        $article->setStatus(ArticleStatus::InCorrection);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::ReviewApproved, 'Article', $article->getId());
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Rédacteur en chef : Relecture → Création (retour au journaliste).
     */
    public function rejectReview(Article $article, string $reason): void
    {
        $article->setStatus(ArticleStatus::Draft);
        $article->setRejectionReason($reason);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::ReviewRejected, 'Article', $article->getId(), $reason);
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Correcteur : Correction → Publication immédiate.
     */
    public function validateAndPublish(Article $article): void
    {
        if (!$article->getPublishedAt()) {
            $article->setPublishedAt(new \DateTimeImmutable());
        }
        $article->setStatus(ArticleStatus::Published);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Validated, 'Article', $article->getId());
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Correcteur : Correction → Programmée (App\Command\PublishScheduledArticlesCommand
     * publiera automatiquement à l'heure dite).
     */
    public function validateAndSchedule(Article $article, \DateTimeImmutable $publishAt): void
    {
        $article->setStatus(ArticleStatus::Scheduled);
        $article->setPublishedAt($publishAt);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Validated, 'Article', $article->getId());
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Correcteur : Correction → Création (retour au journaliste).
     */
    public function rejectCorrection(Article $article, string $reason): void
    {
        $article->setStatus(ArticleStatus::Draft);
        $article->setRejectionReason($reason);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::CorrectionRejected, 'Article', $article->getId(), $reason);
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Rédacteur en chef / admin : retire un article publié de la
     * diffusion, sans passer par la corbeille — il redevient un brouillon
     * modifiable, pas un contenu en attente de suppression.
     */
    public function unpublish(Article $article): void
    {
        $article->setStatus(ArticleStatus::Draft);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Unpublished, 'Article', $article->getId());
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Rédacteur en chef / admin : retire définitivement un article publié
     * du site public tout en le conservant dans l'admin — contrairement à
     * la corbeille, l'archivage n'est pas une étape vers la suppression.
     */
    public function archive(Article $article): void
    {
        $article->setStatus(ArticleStatus::Archived);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Archived, 'Article', $article->getId());
        $this->afterSave($article, wasPublished: false);
    }

    /**
     * Rédacteur en chef / admin : remet un article archivé en ligne sans
     * repasser par tout le circuit de relecture — il a déjà été validé
     * une première fois. wasPublished=true évite de journaliser une
     * "Publication" alors que Unarchived couvre déjà cet événement.
     */
    public function unarchive(Article $article): void
    {
        $article->setStatus(ArticleStatus::Published);
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Unarchived, 'Article', $article->getId());
        $this->afterSave($article, wasPublished: true);
    }

    /**
     * Permanent delete — only meant to be reachable from the trash
     * ("Supprimer définitivement"), never from the normal article list.
     * Everyday deletion goes through softDelete() instead.
     */
    public function delete(Article $article): void
    {
        $id = $article->getId();
        $this->entityManager->remove($article);
        $this->entityManager->flush();
        $this->afterDelete($id);
    }

    /**
     * Call once removal has already been flushed elsewhere (the API's
     * ArticleDeleteProcessor, via the built-in remove_processor).
     */
    public function afterDelete(?int $id): void
    {
        $this->listingsCache->clear();

        if (null !== $id) {
            $this->activityLogger->log(ActivityAction::Deletion, 'Article', $id);
        }
    }

    /**
     * Moves an article to the trash (see App\Doctrine\SoftDeleteFilter):
     * it disappears from the public site, the admin list, and the API,
     * but the row itself — and everything referencing it — is untouched
     * and can be brought back with restore().
     */
    public function softDelete(Article $article): void
    {
        $article->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->listingsCache->clear();
        $this->activityLogger->log(ActivityAction::Trashed, 'Article', $article->getId());
    }

    /**
     * Brings a trashed article back exactly as it was — status, category,
     * publishedAt, etc. are untouched by softDelete(), so restoring just
     * clears the marker.
     */
    public function restore(Article $article): void
    {
        $article->setDeletedAt(null);
        $this->entityManager->flush();
        $this->listingsCache->clear();
        $this->activityLogger->log(ActivityAction::Restored, 'Article', $article->getId());
    }

    private function slugTakenByAnotherArticle(string $candidate, Article $article): bool
    {
        $existing = $this->articleRepository->findOneBy(['slug' => $candidate]);

        return null !== $existing && $existing !== $article;
    }

    /**
     * An editor's articles must carry their own byline by default; if they
     * attribute the article to a different author (a colleague's profile
     * or a guest contributor), it cannot go live on its own — the intended
     * status is parked in requestedStatus and the article is held back as
     * a Draft, pendingApproval, until an admin signs off (see
     * App\Controller\Admin\ValidationController). An admin's own save is
     * always authoritative and resolves any pending hold immediately.
     */
    private function applyAuthorshipGate(Article $article): void
    {
        $actor = $this->security->getUser();

        if ($actor instanceof User && $this->security->isGranted('ROLE_ADMIN')) {
            $article->setPendingApproval(false);
            $article->setRequestedStatus(null);

            return;
        }

        if (!$actor instanceof User) {
            return;
        }

        $ownAuthor = $actor->getAuthor();
        $isOwnByline = null !== $ownAuthor && $article->getAuthor() === $ownAuthor;

        if ($isOwnByline || ArticleStatus::Draft === $article->getStatus()) {
            $article->setPendingApproval(false);
            $article->setRequestedStatus(null);

            return;
        }

        $article->setRequestedStatus($article->getStatus());
        $article->setStatus(ArticleStatus::Draft);
        $article->setPendingApproval(true);
    }
}
