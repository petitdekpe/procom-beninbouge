<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Enum\ArticleStatus;
use App\Form\CommentPublicType;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private CommentRepository $commentRepository,
    ) {
    }

    /**
     * Published articles are public. A non-published one (draft, scheduled,
     * or held pending approval) renders identically — same URL, same
     * template — but only to a logged-in editor, so reviewing an article
     * before it goes live means looking at literally the page it will
     * become, not a separate preview mode that could drift from it.
     */
    #[Route('/article/{slug}', name: 'app_article_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $article = $this->articleRepository->findOneBy(['slug' => $slug]);

        if (!$article) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        if (ArticleStatus::Published !== $article->getStatus() && !$this->isGranted('ROLE_EDITOR')) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        $related = $this->articleRepository->findRelated($article, 3);
        $comments = $this->commentRepository->findApprovedByArticle($article);
        $commentForm = $this->createForm(CommentPublicType::class, (new Comment())->setArticle($article));

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'related' => $related,
            'comments' => $comments,
            'commentForm' => $commentForm,
        ]);
    }
}
