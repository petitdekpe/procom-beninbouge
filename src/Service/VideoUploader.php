<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores uploaded videos (articles « Récap », format mobile) under
 * public/uploads/videos and returns the public URL path to store on
 * Article::$videoUrl. Kept apart from the image and document folders for the
 * same reason as App\Service\DocumentUploader. Only mp4/webm are accepted by
 * the calling form, and the extension is derived from the detected MIME
 * type, never from the client-supplied file name.
 */
final class VideoUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/videos')]
        private string $uploadDir,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @throws FileException if the file cannot be moved into place
     */
    public function upload(UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'video/webm' => 'webm',
            'video/mp4', 'video/quicktime', 'video/x-m4v' => 'mp4',
            default => throw new FileException('Format vidéo non supporté : utilisez un fichier MP4 ou WebM.'),
        };

        if (!$this->filesystem->exists($this->uploadDir)) {
            $this->filesystem->mkdir($this->uploadDir);
        }

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->uploadDir, $filename);

        return '/uploads/videos/'.$filename;
    }
}
