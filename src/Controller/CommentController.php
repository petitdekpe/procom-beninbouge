<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Enum\ArticleStatus;
use App\Form\CommentPublicType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommentController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Anonymous — this site has no public reader accounts (see
     * App\Entity\Comment). A submitted comment always starts Pending and
     * is invisible to other visitors until an editor approves it via
     * App\Controller\Admin\CommentController.
     */
    #[Route('/article/{slug}/comments', name: 'app_comment_create', methods: ['POST'])]
    public function create(Request $request, string $slug): Response
    {
        $article = $this->articleRepository->findOneBy(['slug' => $slug]);

        if (!$article || ArticleStatus::Published !== $article->getStatus()) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        $comment = new Comment();
        $comment->setArticle($article);

        $form = $this->createForm(CommentPublicType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Merci, votre commentaire a été envoyé et sera visible après modération.');
        } else {
            $this->addFlash('error', "Le commentaire n'a pas pu être envoyé : vérifiez les champs et réessayez.");
        }

        return $this->redirectToRoute('app_article_show', ['slug' => $slug]);
    }
}
