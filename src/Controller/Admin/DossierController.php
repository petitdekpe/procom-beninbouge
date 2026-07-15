<?php

namespace App\Controller\Admin;

use App\Entity\Dossier;
use App\Form\DossierAdminType;
use App\Repository\DossierRepository;
use App\Service\ImageUploader;
use App\Service\SeoMetadataGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/dossiers')]
final class DossierController extends AbstractController
{
    public function __construct(
        private DossierRepository $dossierRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploader $imageUploader,
        private SeoMetadataGenerator $seo,
    ) {
    }

    #[Route('', name: 'admin_dossier_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/dossier/index.html.twig', [
            'dossiers' => $this->dossierRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_dossier_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $dossier = new Dossier();
        $form = $this->createForm(DossierAdminType::class, $dossier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applySlugDefault($dossier);
            $this->applySeoDefaults($dossier);
            $this->handleCoverImageUpload($dossier, $form);

            $this->entityManager->persist($dossier);
            $this->entityManager->flush();

            $this->addFlash('success', 'Dossier créé.');

            return $this->redirectToRoute('admin_dossier_index');
        }

        return $this->render('admin/dossier/new.html.twig', [
            'dossier' => $dossier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_dossier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dossier $dossier): Response
    {
        $form = $this->createForm(DossierAdminType::class, $dossier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applySlugDefault($dossier);
            $this->applySeoDefaults($dossier);
            $this->handleCoverImageUpload($dossier, $form);

            $this->entityManager->flush();

            $this->addFlash('success', 'Dossier mis à jour.');

            return $this->redirectToRoute('admin_dossier_index');
        }

        return $this->render('admin/dossier/edit.html.twig', [
            'dossier' => $dossier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_dossier_delete', methods: ['POST'])]
    public function delete(Request $request, Dossier $dossier): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->entityManager->remove($dossier);
            $this->entityManager->flush();

            $this->addFlash('success', 'Dossier supprimé.');
        }

        return $this->redirectToRoute('admin_dossier_index');
    }

    private function applySlugDefault(Dossier $dossier): void
    {
        if ($dossier->getSlug() || !$dossier->getTitle()) {
            return;
        }

        $dossier->setSlug($this->seo->uniqueSlug(
            $dossier->getTitle(),
            fn (string $candidate): bool => $this->slugTakenByAnotherDossier($candidate, $dossier),
        ));
    }

    private function slugTakenByAnotherDossier(string $candidate, Dossier $dossier): bool
    {
        $existing = $this->dossierRepository->findOneBy(['slug' => $candidate]);

        return null !== $existing && $existing !== $dossier;
    }

    private function handleCoverImageUpload(Dossier $dossier, FormInterface $form): void
    {
        $file = $form->get('coverImageFile')->getData();
        if ($file instanceof UploadedFile) {
            $dossier->setCoverImage($this->imageUploader->upload($file));
        }
    }

    /**
     * Same "only fill when blank" reasoning as ArticleSaver::applyDefaults().
     */
    private function applySeoDefaults(Dossier $dossier): void
    {
        if (!$dossier->getMetaTitle() && $dossier->getTitle()) {
            $dossier->setMetaTitle($this->seo->metaTitle($dossier->getTitle()));
        }

        if (!$dossier->getMetaDescription()) {
            $source = $dossier->getDescription() ?: $dossier->getTitle();
            if ($source) {
                $dossier->setMetaDescription($this->seo->metaDescription($source));
            }
        }
    }
}
