<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Enum\MediaType;
use App\Entity\Media;
use App\Entity\User;
use App\Form\ArticleAdminType;
use App\Repository\ArticleRepository;
use App\Repository\ArticleRevisionRepository;
use App\Service\ArticleSaver;
use App\Service\EditorProfileProvisioner;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/articles')]
final class ArticleController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleRevisionRepository $articleRevisionRepository,
        private ArticleSaver $articleSaver,
        private EditorProfileProvisioner $editorProfileProvisioner,
        private ImageUploader $imageUploader,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_article_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/article/index.html.twig', [
            'articles' => $this->articleRepository->findBy([], ['id' => 'DESC']),
            'trashedCount' => $this->articleRepository->countTrashed(),
        ]);
    }

    #[Route('/corbeille', name: 'admin_article_trash', methods: ['GET'])]
    public function trash(): Response
    {
        return $this->render('admin/article/trash.html.twig', [
            'articles' => $this->articleRepository->findTrashed(),
        ]);
    }

    #[Route('/new', name: 'admin_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $article = new Article();

        $user = $this->getUser();
        if ($user instanceof User && !$this->isGranted('ROLE_ADMIN')) {
            $article->setAuthor($this->editorProfileProvisioner->ensureProfile($user));
        }

        $form = $this->createForm(ArticleAdminType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedCover = $this->handleCoverImageUpload($article, $form);

            $wasPublished = $this->articleSaver->applyDefaults($article);
            $this->articleSaver->save($article, $wasPublished);

            if ($uploadedCover) {
                $this->createCoverMedia($article);
            }

            $this->addFlash('success', $article->isPendingApproval()
                ? 'Article enregistré comme brouillon : il est attribué à un autre auteur et devra être validé par un administrateur avant diffusion.'
                : 'Article créé.');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article): Response
    {
        $form = $this->createForm(ArticleAdminType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedCover = $this->handleCoverImageUpload($article, $form);

            $wasPublished = $this->articleSaver->applyDefaults($article);
            $this->articleSaver->save($article, $wasPublished);

            if ($uploadedCover) {
                $this->createCoverMedia($article);
            }

            $this->addFlash('success', $article->isPendingApproval()
                ? 'Article enregistré comme brouillon : il est attribué à un autre auteur et devra être validé par un administrateur avant diffusion.'
                : 'Article mis à jour.');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/history', name: 'admin_article_history', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function history(Article $article): Response
    {
        return $this->render('admin/article/history.html.twig', [
            'article' => $article,
            'revisions' => $this->articleRevisionRepository->findByArticle($article),
        ]);
    }

    #[Route('/{id}/history/{revisionId}/restore', name: 'admin_article_restore_revision', methods: ['POST'], requirements: ['id' => '\d+', 'revisionId' => '\d+'])]
    public function restoreRevision(Request $request, Article $article, int $revisionId): Response
    {
        $revision = $this->articleRevisionRepository->find($revisionId);

        if ($revision && $revision->getArticle() === $article && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->restoreRevision($article, $revision);
            $this->addFlash('success', 'Version restaurée. Le titre, le chapô et le contenu ont été remis à cet état — le statut de publication n\'a pas été modifié.');
        }

        return $this->redirectToRoute('admin_article_history', ['id' => $article->getId()]);
    }

    #[Route('/{id}/submit-for-review', name: 'admin_article_submit_for_review', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submitForReview(Request $request, Article $article): Response
    {
        if (ArticleStatus::Draft !== $article->getStatus() || !$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_article_index');
        }

        if (!$article->getTitle() || !$article->getContent()) {
            $this->addFlash('error', 'Titre et contenu sont obligatoires avant de soumettre pour relecture.');

            return $this->redirectToRoute('admin_article_edit', ['id' => $article->getId()]);
        }

        $this->articleSaver->submitForReview($article);
        $this->addFlash('success', 'Article soumis pour relecture au rédacteur en chef.');

        return $this->redirectToRoute('admin_article_index');
    }

    #[IsGranted('ROLE_EDITOR_IN_CHIEF')]
    #[Route('/{id}/unpublish', name: 'admin_article_unpublish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unpublish(Request $request, Article $article): Response
    {
        if (ArticleStatus::Published === $article->getStatus() && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->unpublish($article);
            $this->addFlash('success', 'Article dépublié : repassé en brouillon.');
        }

        return $this->redirectToRoute('admin_article_index');
    }

    #[IsGranted('ROLE_EDITOR_IN_CHIEF')]
    #[Route('/{id}/archive', name: 'admin_article_archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(Request $request, Article $article): Response
    {
        if (ArticleStatus::Published === $article->getStatus() && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->archive($article);
            $this->addFlash('success', 'Article archivé : retiré du site public, conservé dans l\'admin.');
        }

        return $this->redirectToRoute('admin_article_index');
    }

    #[IsGranted('ROLE_EDITOR_IN_CHIEF')]
    #[Route('/{id}/unarchive', name: 'admin_article_unarchive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unarchive(Request $request, Article $article): Response
    {
        if (ArticleStatus::Archived === $article->getStatus() && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->unarchive($article);
            $this->addFlash('success', 'Article désarchivé : de nouveau publié.');
        }

        return $this->redirectToRoute('admin_article_index');
    }

    #[Route('/{id}/delete', name: 'admin_article_delete', methods: ['POST'])]
    public function delete(Request $request, Article $article): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->softDelete($article);
            $this->addFlash('success', 'Article déplacé dans la corbeille.');
        }

        return $this->redirectToRoute('admin_article_index');
    }

    #[Route('/{id}/restore', name: 'admin_article_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(Request $request, int $id): Response
    {
        $article = $this->articleRepository->findTrashedById($id);

        if ($article && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->restore($article);
            $this->addFlash('success', 'Article restauré.');
        }

        return $this->redirectToRoute('admin_article_trash');
    }

    #[Route('/{id}/force-delete', name: 'admin_article_force_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function forceDelete(Request $request, int $id): Response
    {
        $article = $this->articleRepository->findTrashedById($id);

        if ($article && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->articleSaver->delete($article);
            $this->addFlash('success', 'Article supprimé définitivement.');
        }

        return $this->redirectToRoute('admin_article_trash');
    }

    /**
     * Moves an uploaded cover-image file into place and points
     * Article::$coverImage at it, taking priority over a hand-typed URL.
     */
    private function handleCoverImageUpload(Article $article, FormInterface $form): bool
    {
        $file = $form->get('coverImageFile')->getData();
        if (!$file instanceof UploadedFile) {
            return false;
        }

        $article->setCoverImage($this->imageUploader->upload($file));

        return true;
    }

    /**
     * An uploaded cover image is also a piece of media in its own right —
     * it must show up in the Médias list, not just on the article.
     */
    private function createCoverMedia(Article $article): void
    {
        $media = new Media();
        $media->setType(MediaType::Image);
        $media->setArticle($article);
        $media->setImageUrl($article->getCoverImage());
        $media->setTitle($article->getTitle());

        $this->entityManager->persist($media);
        $this->entityManager->flush();
    }
}
