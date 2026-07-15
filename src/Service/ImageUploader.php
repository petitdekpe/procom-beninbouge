<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores uploaded images under public/uploads/images and returns the public
 * URL path to store on Article::$coverImage or Media::$imageUrl — both of
 * which are otherwise free-text URL fields, so an uploaded file and a
 * hand-typed external URL are interchangeable from their point of view.
 *
 * Every image is re-encoded to WebP on the way in (see convertToWebp()),
 * regardless of the source format (JPEG/PNG/GIF/WebP all pass the same
 * mimeTypes constraint on the calling forms) — callers never deal with the
 * original format, only ever with the resulting .webp file and URL.
 *
 * upload() does both steps at once for callers with no validation timing
 * constraint (App\Controller\Admin\ArticleController). Where an uploaded
 * file's resulting URL must already be on the entity *before* Symfony's
 * own form validation runs (App\Form\MediaAdminType, whose Media entity
 * requires imageUrl to be non-blank for type=image), reserveFilename()/
 * urlFor() let the URL be assigned without touching the filesystem yet —
 * the physical conversion happens via store(), once the whole form is
 * confirmed valid, using the exact same filename so the two stay in sync.
 */
final class ImageUploader
{
    private const QUALITY = 82;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/images')]
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

    /**
     * Always reserves a .webp filename — the source extension is irrelevant
     * since store() re-encodes to WebP regardless of the original format.
     */
    public function reserveFilename(UploadedFile $file): string
    {
        return bin2hex(random_bytes(16)).'.webp';
    }

    public function urlFor(string $filename): string
    {
        return '/uploads/images/'.$filename;
    }

    /**
     * @throws FileException if the file cannot be read, converted or written
     */
    public function store(UploadedFile $file, string $filename): void
    {
        if (!$this->filesystem->exists($this->uploadDir)) {
            $this->filesystem->mkdir($this->uploadDir);
        }

        $this->convertToWebp($file, $this->uploadDir.\DIRECTORY_SEPARATOR.$filename);
    }

    private function convertToWebp(UploadedFile $file, string $targetPath): void
    {
        $sourcePath = $file->getPathname();

        $image = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => throw new FileException(\sprintf('Format d\'image non supporté : %s', $file->getMimeType() ?? 'inconnu')),
        };

        if (false === $image) {
            throw new FileException('Impossible de lire le fichier image.');
        }

        // Preserves transparency (PNG/GIF) through the re-encode instead of
        // flattening it to a solid background.
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $written = imagewebp($image, $targetPath, self::QUALITY);
        imagedestroy($image);

        if (!$written) {
            throw new FileException('Échec de la conversion de l\'image en WebP.');
        }
    }
}
