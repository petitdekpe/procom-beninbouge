<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Enum\ActivityAction;
use App\Entity\Enum\ArticleStatus;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Service\ActivityLogger;
use App\Service\ArticleSaver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The admin-only review queue: editor profile-change requests
 * (Author::$pending*) and articles attributed to someone other than their
 * author (Article::$pendingApproval) both land here until an admin
 * approves or rejects them.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/validations')]
final class ValidationController extends AbstractController
{
    public function __construct(
        private AuthorRepository $authorRepository,
        private ArticleRepository $articleRepository,
        private EntityManagerInterface $entityManager,
        private ArticleSaver $articleSaver,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'admin_validation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/validation/index.html.twig', [
            'pendingProfiles' => $this->authorRepository->findPendingProfileChanges(),
            'pendingArticles' => $this->articleRepository->findBy(['pendingApproval' => true], ['id' => 'DESC']),
        ]);
    }

    #[Route('/profiles/{id}/approve', name: 'admin_validation_profile_approve', methods: ['POST'])]
    public function approveProfile(Request $request, Author $author): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $author->approvePendingChanges();
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'Author', $author->getId(), 'profil validé');
            $this->addFlash('success', 'Modification de profil validée.');
        }

        return $this->redirectToRoute('admin_validation_index');
    }

    #[Route('/profiles/{id}/reject', name: 'admin_validation_profile_reject', methods: ['POST'])]
    public function rejectProfile(Request $request, Author $author): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $author->clearPendingChanges();
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'Author', $author->getId(), 'profil rejeté');
            $this->addFlash('success', 'Modification de profil rejetée.');
        }

        return $this->redirectToRoute('admin_validation_index');
    }

    #[Route('/articles/{id}/approve', name: 'admin_validation_article_approve', methods: ['POST'])]
    public function approveArticle(Request $request, Article $article): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $article->setStatus($article->getRequestedStatus() ?? ArticleStatus::Published);
            if (ArticleStatus::Published === $article->getStatus() && !$article->getPublishedAt()) {
                $article->setPublishedAt(new \DateTimeImmutable());
            }
            $article->setPendingApproval(false);
            $article->setRequestedStatus(null);
            $admin = $this->getUser();
            $article->setApprovedBy($admin instanceof User ? $admin : null);
            $article->setApprovedAt(new \DateTimeImmutable());

            $this->articleSaver->save($article, false);
            $this->activityLogger->log(ActivityAction::Moderation, 'Article', $article->getId(), 'article validé');
            $this->addFlash('success', 'Article validé et diffusé selon le statut demandé.');
        }

        return $this->redirectToRoute('admin_validation_index');
    }

    #[Route('/articles/{id}/reject', name: 'admin_validation_article_reject', methods: ['POST'])]
    public function rejectArticle(Request $request, Article $article): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $article->setPendingApproval(false);
            $article->setRequestedStatus(null);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'Article', $article->getId(), 'article rejeté');
            $this->addFlash('success', 'Article rejeté : il reste en brouillon.');
        }

        return $this->redirectToRoute('admin_validation_index');
    }
}
