<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Form\MediaAdminType;
use App\Repository\MediaRepository;
use App\Service\DocumentUploader;
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
#[Route('/admin/media')]
final class MediaController extends AbstractController
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploader $imageUploader,
        private DocumentUploader $documentUploader,
    ) {
    }

    #[Route('', name: 'admin_media_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/media/index.html.twig', [
            'media' => $this->mediaRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_media_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $media = new Media();
        $form = $this->createForm(MediaAdminType::class, $media);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeUploadedImage($media, $form);
            $this->storeUploadedThumbnail($media, $form);
            $this->storeUploadedDocument($media, $form);

            $this->entityManager->persist($media);
            $this->entityManager->flush();

            $this->addFlash('success', 'Média créé.');

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->render('admin/media/new.html.twig', [
            'media_item' => $media,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_media_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Media $media): Response
    {
        $form = $this->createForm(MediaAdminType::class, $media);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeUploadedImage($media, $form);
            $this->storeUploadedThumbnail($media, $form);
            $this->storeUploadedDocument($media, $form);

            $this->entityManager->flush();

            $this->addFlash('success', 'Média mis à jour.');

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->render('admin/media/edit.html.twig', [
            'media_item' => $media,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_media_delete', methods: ['POST'])]
    public function delete(Request $request, Media $media): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->entityManager->remove($media);
            $this->entityManager->flush();

            $this->addFlash('success', 'Média supprimé.');
        }

        return $this->redirectToRoute('admin_media_index');
    }

    /**
     * MediaAdminType already pointed Media::$imageUrl at the eventual
     * /uploads/images/<filename> path (needed before validation ran) —
     * this performs the actual filesystem move, now that the whole form
     * is confirmed valid, using that same filename.
     */
    private function storeUploadedImage(Media $media, FormInterface $form): void
    {
        $file = $form->get('imageFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $filename = basename((string) $media->getImageUrl());
        $this->imageUploader->store($file, $filename);
    }

    /**
     * thumbnail has no blocking entity validation (a video can be saved
     * without one — see Media::getEffectiveThumbnail()), so unlike
     * imageUrl this can use the simple single-step upload directly here.
     */
    private function storeUploadedThumbnail(Media $media, FormInterface $form): void
    {
        $file = $form->get('thumbnailFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $media->setThumbnail($this->imageUploader->upload($file));
    }

    /**
     * Same two-step reasoning as storeUploadedImage(): MediaAdminType
     * already pointed Media::$documentUrl at the eventual
     * /uploads/documents/<filename> path before validation ran, this just
     * performs the actual filesystem move now that the form is valid.
     */
    private function storeUploadedDocument(Media $media, FormInterface $form): void
    {
        $file = $form->get('documentFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $filename = basename((string) $media->getDocumentUrl());
        $this->documentUploader->store($file, $filename);
    }
}
