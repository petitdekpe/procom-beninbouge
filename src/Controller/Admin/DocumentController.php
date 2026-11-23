<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Form\DocumentAdminType;
use App\Repository\DocumentRepository;
use App\Service\DocumentLibrary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Documenthèque : chaque document a un nom et un lien de téléchargement
 * direct /documents/{slug}, à coller n'importe où (article, réseaux,
 * newsletter…) ou à joindre à un article depuis son formulaire.
 */
#[IsGranted('media.manage')]
#[Route('/admin/documents')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentLibrary $documentLibrary,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_document_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/document/index.html.twig', [
            'documents' => $this->documentRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $document = new Document();
        $form = $this->createForm(DocumentAdminType::class, $document, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();

            $this->documentLibrary->assignSlug($document);
            $this->documentLibrary->attachFile($document, $file);
            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->addFlash('success', 'Document ajouté. Copiez son lien de téléchargement dans la liste ci-dessous.');

            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('admin/document/new.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_document_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Document $document): Response
    {
        $form = $this->createForm(DocumentAdminType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            if ($file instanceof UploadedFile) {
                $this->documentLibrary->attachFile($document, $file);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Document mis à jour.');

            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('admin/document/edit.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_document_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Document $document): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->documentLibrary->remove($document);

            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('admin_document_index');
    }
}
