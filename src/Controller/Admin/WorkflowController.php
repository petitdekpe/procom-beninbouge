<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\ArticleSaver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The two review queues of the editorial workflow (Journaliste → Rédacteur
 * en chef → Correcteur → Publication — see App\Service\ArticleSaver for
 * the transitions themselves). Distinct from App\Controller\Admin\ValidationController,
 * which is the narrower "different byline" approval gate.
 */
#[Route('/admin/workflow')]
final class WorkflowController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleSaver $articleSaver,
    ) {
    }

    #[IsGranted('ROLE_EDITOR_IN_CHIEF')]
    #[Route('/relecture', name: 'admin_workflow_review', methods: ['GET'])]
    public function review(): Response
    {
        return $this->render('admin/workflow/review.html.twig', [
            'articles' => $this->articleRepository->findBy(['status' => ArticleStatus::InReview], ['id' => 'DESC']),
        ]);
    }

    #[IsGranted('ROLE_EDITOR_IN_CHIEF')]
    #[Route('/relecture/{id}/approve', name: 'admin_workflow_review_approve', methods: ['POST'])]
    public function approveReview(Request $request, Article $article): Response
    {
        if (ArticleStatus::InReview === $article->getStatus() && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->approveReview($article);
            $this->addFlash('success', 'Article envoyé en correction.');
        }

        return $this->redirectToRoute('admin_workflow_review');
    }

    #[IsGranted('ROLE_EDITOR_IN_CHIEF')]
    #[Route('/relecture/{id}/reject', name: 'admin_workflow_review_reject', methods: ['POST'])]
    public function rejectReview(Request $request, Article $article): Response
    {
        $reason = trim((string) $request->request->get('reason'));

        if ('' === $reason) {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');

            return $this->redirectToRoute('admin_workflow_review');
        }

        if (ArticleStatus::InReview === $article->getStatus() && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->rejectReview($article, $reason);
            $this->addFlash('success', 'Article refusé et renvoyé au journaliste.');
        }

        return $this->redirectToRoute('admin_workflow_review');
    }

    #[IsGranted('ROLE_PROOFREADER')]
    #[Route('/correction', name: 'admin_workflow_correction', methods: ['GET'])]
    public function correction(): Response
    {
        return $this->render('admin/workflow/correction.html.twig', [
            'articles' => $this->articleRepository->findBy(['status' => ArticleStatus::InCorrection], ['id' => 'DESC']),
        ]);
    }

    #[IsGranted('ROLE_PROOFREADER')]
    #[Route('/correction/{id}/validate', name: 'admin_workflow_correction_validate', methods: ['POST'])]
    public function validateCorrection(Request $request, Article $article): Response
    {
        if (ArticleStatus::InCorrection !== $article->getStatus() || !$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_workflow_correction');
        }

        if ('schedule' === $request->request->get('mode')) {
            $publishAt = $this->parsePublishAt((string) $request->request->get('publish_at'));

            if (!$publishAt) {
                $this->addFlash('error', 'Date de publication invalide.');

                return $this->redirectToRoute('admin_workflow_correction');
            }

            $this->articleSaver->validateAndSchedule($article, $publishAt);
            $this->addFlash('success', sprintf('Article validé, publication programmée pour le %s.', $publishAt->format('d/m/Y à H:i')));
        } else {
            $this->articleSaver->validateAndPublish($article);
            $this->addFlash('success', 'Article validé et publié.');
        }

        return $this->redirectToRoute('admin_workflow_correction');
    }

    #[IsGranted('ROLE_PROOFREADER')]
    #[Route('/correction/{id}/reject', name: 'admin_workflow_correction_reject', methods: ['POST'])]
    public function rejectCorrection(Request $request, Article $article): Response
    {
        $reason = trim((string) $request->request->get('reason'));

        if ('' === $reason) {
            $this->addFlash('error', 'Un motif de refus est obligatoire.');

            return $this->redirectToRoute('admin_workflow_correction');
        }

        if (ArticleStatus::InCorrection === $article->getStatus() && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->rejectCorrection($article, $reason);
            $this->addFlash('success', 'Article refusé et renvoyé au journaliste.');
        }

        return $this->redirectToRoute('admin_workflow_correction');
    }

    private function parsePublishAt(string $value): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
