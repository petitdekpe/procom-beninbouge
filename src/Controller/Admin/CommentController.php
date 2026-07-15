<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Entity\Enum\ActivityAction;
use App\Entity\Enum\CommentStatus;
use App\Repository\CommentRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/comments')]
final class CommentController extends AbstractController
{
    public function __construct(
        private CommentRepository $commentRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'admin_comment_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = CommentStatus::tryFrom((string) $request->query->get('status')) ?? CommentStatus::Pending;

        return $this->render('admin/comment/index.html.twig', [
            'comments' => $this->commentRepository->findByStatus($status),
            'currentStatus' => $status,
            'pendingCount' => $this->commentRepository->countByStatus(CommentStatus::Pending),
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_comment_approve', methods: ['POST'])]
    public function approve(Request $request, Comment $comment): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $comment->setStatus(CommentStatus::Approved);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'Comment', $comment->getId(), 'status=approved');

            $this->addFlash('success', 'Commentaire approuvé.');
        }

        return $this->redirectToRoute('admin_comment_index', ['status' => $request->query->get('status')]);
    }

    #[Route('/{id}/reject', name: 'admin_comment_reject', methods: ['POST'])]
    public function reject(Request $request, Comment $comment): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $comment->setStatus(CommentStatus::Rejected);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'Comment', $comment->getId(), 'status=rejected');

            $this->addFlash('success', 'Commentaire rejeté.');
        }

        return $this->redirectToRoute('admin_comment_index', ['status' => $request->query->get('status')]);
    }

    #[Route('/{id}/delete', name: 'admin_comment_delete', methods: ['POST'])]
    public function delete(Request $request, Comment $comment): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $id = $comment->getId();
            $this->entityManager->remove($comment);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Deletion, 'Comment', $id);

            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('admin_comment_index', ['status' => $request->query->get('status')]);
    }
}
