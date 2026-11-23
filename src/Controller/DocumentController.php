<?php

namespace App\Controller;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\DocumentLibrary;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lien de téléchargement direct d'un document de la documenthèque.
 */
final class DocumentController extends AbstractController
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|whatsapp|telegram|skypeuripreview|embedly|preview|curl|wget|python-requests|headless/i';

    #[Route('/documents/{slug}', name: 'app_document_download', methods: ['GET'])]
    public function download(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Document $document,
        DocumentLibrary $documentLibrary,
        DocumentRepository $documentRepository,
    ): Response {
        $path = $documentLibrary->absolutePath($document);
        if (!is_file($path)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        if ($this->isRealDownload($request)) {
            $documentRepository->incrementDownloadCount($document);
        }

        BinaryFileResponse::trustXSendfileTypeHeader();

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $documentLibrary->downloadFilename($document));
        if ($document->getMimeType()) {
            $response->headers->set('Content-Type', $document->getMimeType());
        }
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * Ne compte pas les requêtes HEAD ni les robots : moteurs de recherche
     * et aperçus de liens (WhatsApp, Facebook, X, Telegram…) appellent
     * l'URL dès qu'elle est partagée, sans que personne ne télécharge.
     */
    private function isRealDownload(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        $userAgent = (string) $request->headers->get('User-Agent');
        if ('' === $userAgent) {
            return false;
        }

        return 1 !== preg_match(self::BOT_PATTERN, $userAgent);
    }
}
