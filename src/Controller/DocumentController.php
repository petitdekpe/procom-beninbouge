<?php

namespace App\Controller;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\DocumentLibrary;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lien de téléchargement direct d'un document de la documenthèque.
 */
final class DocumentController extends AbstractController
{
    #[Route('/documents/{slug}', name: 'app_document_download', methods: ['GET'])]
    public function download(
        #[MapEntity(mapping: ['slug' => 'slug'])] Document $document,
        DocumentLibrary $documentLibrary,
        DocumentRepository $documentRepository,
    ): Response {
        $path = $documentLibrary->absolutePath($document);
        if (!is_file($path)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $documentRepository->incrementDownloadCount($document);

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $documentLibrary->downloadFilename($document));
        if ($document->getMimeType()) {
            $response->headers->set('Content-Type', $document->getMimeType());
        }
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
