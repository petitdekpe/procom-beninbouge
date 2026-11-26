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
    private const AVATAR_SIZE = 480;

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
     * Photo de profil : redressée selon l'orientation EXIF (photos prises au
     * téléphone), recadrée en carré et réduite, car elle s'affiche en petit
     * sur chaque carte d'article.
     */
    public function uploadAvatar(UploadedFile $file): string
    {
        $image = $this->decode($file);
        imagepalettetotruecolor($image);

        // Réduction avant rotation, pour limiter la mémoire consommée.
        $shortSide = min(imagesx($image), imagesy($image));
        if ($shortSide > self::AVATAR_SIZE) {
            $ratio = self::AVATAR_SIZE / $shortSide;
            $scaled = imagecreatetruecolor((int) round(imagesx($image) * $ratio), (int) round(imagesy($image) * $ratio));
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagecopyresampled($scaled, $image, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), imagesx($image), imagesy($image));
            $image = $scaled;
        }

        $image = $this->applyExifOrientation($image, $file);

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        // Centré en largeur ; en portrait, cadré vers le haut pour garder le visage.
        $square = imagecrop($image, [
            'x' => intdiv($width - $side, 2),
            'y' => intdiv($height - $side, 4),
            'width' => $side,
            'height' => $side,
        ]);

        if (false === $square) {
            throw new FileException('Impossible de recadrer la photo.');
        }

        $filename = $this->reserveFilename($file);
        $this->ensureUploadDir();
        $this->writeWebp($square, $this->uploadDir.\DIRECTORY_SEPARATOR.$filename);

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
        $this->ensureUploadDir();
        $this->convertToWebp($file, $this->uploadDir.\DIRECTORY_SEPARATOR.$filename);
    }

    private function ensureUploadDir(): void
    {
        if (!$this->filesystem->exists($this->uploadDir)) {
            $this->filesystem->mkdir($this->uploadDir);
        }
    }

    private function convertToWebp(UploadedFile $file, string $targetPath): void
    {
        $this->writeWebp($this->decode($file), $targetPath);
    }

    private function decode(UploadedFile $file): \GdImage
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

        return $image;
    }

    private function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ('image/jpeg' !== $file->getMimeType() || !\function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getPathname());
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return 0 === $angle ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private function writeWebp(\GdImage $image, string $targetPath): void
    {
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
