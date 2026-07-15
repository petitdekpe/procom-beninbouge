<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores uploaded PDF documents under public/uploads/documents and returns
 * the public URL path to store on Media::$documentUrl — a separate folder
 * from App\Service\ImageUploader's public/uploads/images so anything that
 * assumes that folder only holds images (thumbnails, image processing)
 * never has to account for PDFs living alongside them. Same two-step
 * reserve/store split for the same reason: Media's validation requires
 * documentUrl to be non-blank *before* the form is confirmed valid, so the
 * URL has to exist ahead of the actual filesystem move.
 */
final class DocumentUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/documents')]
        private string $uploadDir,
        private Filesystem $filesystem,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $filename = $this->reserveFilename($file);
        $this->store($file, $filename);

        return $this->urlFor($filename);
    }

    public function reserveFilename(UploadedFile $file): string
    {
        return bin2hex(random_bytes(16)).'.'.($file->guessExtension() ?: 'pdf');
    }

    public function urlFor(string $filename): string
    {
        return '/uploads/documents/'.$filename;
    }

    /**
     * @throws FileException if the file cannot be moved into place
     */
    public function store(UploadedFile $file, string $filename): void
    {
        if (!$this->filesystem->exists($this->uploadDir)) {
            $this->filesystem->mkdir($this->uploadDir);
        }

        $file->move($this->uploadDir, $filename);
    }
}
