<?php

namespace App\Service;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\File;

/**
 * Documenthèque : crée les App\Entity\Document à partir des fichiers
 * téléversés (depuis Admin > Documents ou depuis le formulaire d'un
 * article), remplace leur fichier et les supprime avec leur fichier.
 *
 * Les fichiers sont rangés dans public/uploads/documents via
 * App\Service\DocumentUploader, sous un nom aléatoire ; le lien public
 * reste toujours /documents/{slug} (App\Controller\DocumentController), qui
 * sert le fichier sous un nom lisible tiré du nom du document.
 */
final class DocumentLibrary
{
    public const MAX_SIZE = '50M';

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-office',
        'application/CDFV2',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/rtf',
        'text/rtf',
        'text/plain',
        'text/csv',
        'application/zip',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const ALLOWED_FORMATS_LABEL = 'PDF, Word, Excel, PowerPoint, OpenDocument, RTF, TXT, CSV, ZIP ou image';

    public function __construct(
        private DocumentUploader $uploader,
        private DocumentRepository $repository,
        private SluggerInterface $slugger,
        private EntityManagerInterface $entityManager,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {
    }

    public static function fileConstraint(): File
    {
        return new File(
            maxSize: self::MAX_SIZE,
            mimeTypes: self::ALLOWED_MIME_TYPES,
            mimeTypesMessage: 'Format non accepté. Formats acceptés : '.self::ALLOWED_FORMATS_LABEL.'.',
        );
    }

    /**
     * Crée et persiste (sans flush) un document à partir d'un fichier. Sans
     * nom fourni, le nom du fichier d'origine (sans extension) est utilisé.
     */
    public function createFromUpload(UploadedFile $file, ?string $name = null): Document
    {
        $document = new Document();
        $name = trim((string) $name);
        $document->setName('' !== $name ? $name : $this->nameFromFilename($file->getClientOriginalName()));

        $this->assignSlug($document);
        $this->attachFile($document, $file);
        $this->entityManager->persist($document);

        return $document;
    }

    /**
     * Attribue un slug unique à un nouveau document, tiré de son nom. Sans
     * effet sur un document qui a déjà un slug : le lien ne change jamais.
     */
    public function assignSlug(Document $document): void
    {
        if ($document->getSlug()) {
            return;
        }

        $base = $this->slugger->slug((string) $document->getName())->lower()->truncate(160, '', false)->toString();
        if ('' === $base) {
            $base = 'document';
        }

        $slug = $base;
        $i = 2;
        while (null !== $this->repository->findOneBy(['slug' => $slug])) {
            $slug = $base.'-'.$i++;
        }

        $document->setSlug($slug);
    }

    /**
     * Range le fichier et met à jour les métadonnées du document. Si le
     * document avait déjà un fichier, l'ancien est supprimé du disque.
     */
    public function attachFile(Document $document, UploadedFile $file): void
    {
        // Lus avant le déplacement : le fichier temporaire n'existe plus après.
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Extension déduite du contenu réel (et non du nom envoyé par le
        // navigateur), pour ne jamais écrire un .php ou autre dans public/.
        $filename = bin2hex(random_bytes(16)).'.'.($file->guessExtension() ?: 'bin');
        $this->uploader->store($file, $filename);

        $previous = $document->getFilePath();

        $document->setFilePath($this->uploader->urlFor($filename));
        $document->setOriginalFilename($originalName);
        $document->setMimeType($mimeType);
        $document->setSize(false === $size ? null : $size);

        if ($previous) {
            $this->deleteStoredFile($previous);
        }
    }

    /** Supprime le document (flush inclus) et son fichier. */
    public function remove(Document $document): void
    {
        $path = $document->getFilePath();

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        if ($path) {
            $this->deleteStoredFile($path);
        }
    }

    public function absolutePath(Document $document): string
    {
        return $this->publicDir.'/'.ltrim((string) $document->getFilePath(), '/');
    }

    /**
     * Nom de fichier proposé au téléchargement : le nom du document en
     * slug + l'extension du fichier d'origine (plus parlante que celle
     * déduite du contenu, ex. un .docx parfois détecté comme .zip).
     */
    public function downloadFilename(Document $document): string
    {
        $extension = strtolower(pathinfo((string) $document->getOriginalFilename(), \PATHINFO_EXTENSION))
            ?: strtolower(pathinfo((string) $document->getFilePath(), \PATHINFO_EXTENSION));

        $base = $this->slugger->slug((string) $document->getName())->toString() ?: 'document';

        return $extension ? $base.'.'.$extension : $base;
    }

    private function nameFromFilename(string $filename): string
    {
        $name = trim(str_replace(['_', '-'], ' ', pathinfo($filename, \PATHINFO_FILENAME)));

        return '' !== $name ? $name : 'Document';
    }

    private function deleteStoredFile(string $publicPath): void
    {
        // Ne touche qu'aux fichiers de public/uploads/documents.
        if (!str_starts_with($publicPath, $this->uploader->urlFor(''))) {
            return;
        }

        $this->filesystem->remove($this->publicDir.'/'.ltrim($publicPath, '/'));
    }
}
