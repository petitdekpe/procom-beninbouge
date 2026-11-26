<?php

namespace App\Security\Mobile;

use App\Entity\Article;
use App\Entity\Enum\ArticleStatus;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Ce qu'une personne peut faire d'un article depuis l'application mobile.
 * Un seul endroit : les contrôles du serveur et les boutons affichés par
 * l'application (champ « actions » des réponses) en découlent.
 *
 * Qui modifie quoi dépend de l'étape du circuit :
 * brouillon → son auteur ; relecture → rédacteur en chef ; correction →
 * correcteur ; programmé, publié, archivé → permission « articles.publish ».
 * L'administrateur peut tout.
 */
final class ArticleAccess
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function canBrowse(): bool
    {
        return $this->security->isGranted('articles.write')
            || $this->security->isGranted('workflow.review')
            || $this->security->isGranted('workflow.proofread');
    }

    public function isOwn(Article $article): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User
            && null !== $user->getAuthor()
            && $article->getAuthor() === $user->getAuthor();
    }

    public function canEdit(Article $article): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // En attente de validation par un administrateur : on n'y touche plus.
        if ($article->isPendingApproval()) {
            return false;
        }

        return match ($article->getStatus()) {
            ArticleStatus::Draft => $this->security->isGranted('articles.write') && $this->isOwn($article),
            ArticleStatus::InReview => $this->security->isGranted('workflow.review'),
            ArticleStatus::InCorrection => $this->security->isGranted('workflow.proofread'),
            default => $this->security->isGranted('articles.publish'),
        };
    }

    /** Mise à la corbeille uniquement : l'application ne supprime rien définitivement. */
    public function canTrash(Article $article): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('articles.publish')) {
            return true;
        }

        return ArticleStatus::Draft === $article->getStatus()
            && !$article->isPendingApproval()
            && $this->security->isGranted('articles.write')
            && $this->isOwn($article);
    }

    /**
     * @return list<string> les actions du circuit ouvertes à la personne connectée
     */
    public function actions(Article $article): array
    {
        $admin = $this->security->isGranted('ROLE_ADMIN');
        $status = $article->getStatus();
        $actions = [];

        if (ArticleStatus::Draft === $status && !$article->isPendingApproval()
            && $this->security->isGranted('articles.write') && ($admin || $this->isOwn($article))) {
            $actions[] = 'submit';
        }
        if (ArticleStatus::InReview === $status && $this->security->isGranted('workflow.review')) {
            $actions[] = 'approve_review';
            $actions[] = 'reject_review';
        }

        $proofreading = ArticleStatus::InCorrection === $status && $this->security->isGranted('workflow.proofread');
        $adminShortcut = $admin && \in_array($status, [ArticleStatus::Draft, ArticleStatus::InReview, ArticleStatus::InCorrection, ArticleStatus::Scheduled], true);
        if ($proofreading || $adminShortcut) {
            $actions[] = 'publish';
            $actions[] = 'schedule';
        }
        if ($proofreading) {
            $actions[] = 'reject_correction';
        }

        if ($this->security->isGranted('articles.publish')) {
            if (ArticleStatus::Published === $status) {
                $actions[] = 'unpublish';
                $actions[] = 'archive';
            }
            if (ArticleStatus::Scheduled === $status) {
                $actions[] = 'unschedule';
            }
            if (ArticleStatus::Archived === $status) {
                $actions[] = 'unarchive';
            }
        }

        return $actions;
    }
}
