<?php

namespace App\Controller\Admin;

use App\Entity\Enum\ArticleStatus;
use App\Entity\Recap;
use App\Form\RecapAdminType;
use App\Repository\RecapRepository;
use App\Service\ImageUploader;
use App\Service\SeoMetadataGenerator;
use App\Service\VideoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Récaps : vidéos au format mobile avec leur texte. Un CRUD simple, sans le
 * workflow éditorial des articles (brouillon ou publié, c'est tout).
 */
#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/recaps')]
final class RecapController extends AbstractController
{
    public function __construct(
        private RecapRepository $recapRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploader $imageUploader,
        private VideoUploader $videoUploader,
        private SeoMetadataGenerator $seo,
    ) {
    }

    #[Route('', name: 'admin_recap_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/recap/index.html.twig', [
            'recaps' => $this->recapRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_recap_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $recap = new Recap();
        $form = $this->createForm(RecapAdminType::class, $recap);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->handleVideo($recap, $form)) {
            $this->handleCoverImageUpload($recap, $form);
            $this->applyDefaults($recap);

            $this->entityManager->persist($recap);
            $this->entityManager->flush();

            $this->addFlash('success', 'Récap créé.');

            return $this->redirectToRoute('admin_recap_index');
        }

        return $this->render('admin/recap/new.html.twig', [
            'recap' => $recap,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_recap_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Recap $recap): Response
    {
        $form = $this->createForm(RecapAdminType::class, $recap);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->handleVideo($recap, $form)) {
            $this->handleCoverImageUpload($recap, $form);
            $this->applyDefaults($recap);

            $this->entityManager->flush();

            $this->addFlash('success', 'Récap mis à jour.');

            return $this->redirectToRoute('admin_recap_index');
        }

        return $this->render('admin/recap/edit.html.twig', [
            'recap' => $recap,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_recap_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Recap $recap): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->entityManager->remove($recap);
            $this->entityManager->flush();

            $this->addFlash('success', 'Récap supprimé.');
        }

        return $this->redirectToRoute('admin_recap_index');
    }

    /**
     * Stores an uploaded video (it takes priority over a typed link) and
     * makes sure the récap ends up with a playable one. Returns false — with
     * an error on the form, which is then shown again — when it doesn't.
     */
    private function handleVideo(Recap $recap, FormInterface $form): bool
    {
        $file = $form->get('videoFile')->getData();
        if ($file instanceof UploadedFile) {
            try {
                $recap->setVideoUrl($this->videoUploader->upload($file));
            } catch (FileException $e) {
                $form->get('videoFile')->addError(new FormError($e->getMessage()));

                return false;
            }
        }

        if (!$recap->hasVideo()) {
            $form->get('videoUrl')->addError(new FormError('Un récap a besoin d\'une vidéo : collez un lien YouTube ou téléversez un fichier.'));

            return false;
        }

        return true;
    }

    private function handleCoverImageUpload(Recap $recap, FormInterface $form): void
    {
        $file = $form->get('coverImageFile')->getData();
        if ($file instanceof UploadedFile) {
            $recap->setCoverImage($this->imageUploader->upload($file));
        }
    }

    /**
     * Same "only fill when blank" reasoning as ArticleSaver::applyDefaults().
     */
    private function applyDefaults(Recap $recap): void
    {
        if (!$recap->getSlug() && $recap->getTitle()) {
            $recap->setSlug($this->seo->uniqueSlug(
                $recap->getTitle(),
                function (string $candidate) use ($recap): bool {
                    $existing = $this->recapRepository->findOneBy(['slug' => $candidate]);

                    return null !== $existing && $existing !== $recap;
                },
            ));
        }

        if (!$recap->getMetaTitle() && $recap->getTitle()) {
            $recap->setMetaTitle($this->seo->metaTitle($recap->getTitle()));
        }

        if (!$recap->getMetaDescription() && $recap->getContent()) {
            $recap->setMetaDescription($this->seo->metaDescription($recap->getContent()));
        }

        if (ArticleStatus::Published === $recap->getStatus() && !$recap->getPublishedAt()) {
            $recap->setPublishedAt(new \DateTimeImmutable());
        }
    }
}
