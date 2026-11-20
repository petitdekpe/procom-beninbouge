<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

/**
 * Extrait une image d'une vidéo téléversée (public/uploads/videos) avec
 * ffmpeg et la range comme n'importe quelle image téléversée (conversion
 * WebP par App\Service\ImageUploader). ffmpeg n'est pas garanti sur tous les
 * hébergements : sans lui, isAvailable() est faux, rien n'est généré côté
 * serveur et c'est l'admin qui génère l'image depuis le navigateur (voir
 * assets/controllers/recap_cover_controller.js).
 *
 * Le binaire se règle par FFMPEG_BINARY (.env), « ffmpeg » par défaut.
 */
final class VideoFrameExtractor
{
    private ?bool $available = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
        #[Autowire(env: 'FFMPEG_BINARY')]
        private string $binary,
        private ImageUploader $imageUploader,
        private Filesystem $filesystem,
    ) {
    }

    public function isAvailable(): bool
    {
        if (null === $this->available) {
            try {
                $process = new Process([$this->binary, '-version']);
                $process->setTimeout(10);
                $process->run();
                $this->available = $process->isSuccessful();
            } catch (\Throwable) {
                $this->available = false;
            }
        }

        return $this->available;
    }

    /**
     * Whether the URL is an uploaded video file this service can read.
     */
    public function supports(?string $videoUrl): bool
    {
        return null !== $videoUrl
            && 1 === preg_match('#^/uploads/videos/[A-Za-z0-9._-]+$#', $videoUrl)
            && is_file($this->publicDir.$videoUrl);
    }

    /**
     * Extracts a frame (about one second in, or the first one for a very
     * short clip) and returns the public URL of the stored image, or null
     * when ffmpeg is missing or fails.
     */
    public function extractCover(string $videoUrl): ?string
    {
        if (!$this->isAvailable() || !$this->supports($videoUrl)) {
            return null;
        }

        $frame = $this->filesystem->tempnam(sys_get_temp_dir(), 'recap-frame-', '.jpg');

        try {
            foreach (['1', '0'] as $offset) {
                $process = new Process([
                    $this->binary, '-y', '-loglevel', 'error',
                    '-ss', $offset, '-i', $this->publicDir.$videoUrl,
                    '-frames:v', '1', '-q:v', '3', $frame,
                ]);
                $process->setTimeout(60);
                $process->run();

                if ($process->isSuccessful() && filesize($frame) > 0) {
                    return $this->imageUploader->upload(new UploadedFile($frame, 'couverture.jpg', 'image/jpeg', null, true));
                }
            }
        } catch (\Throwable) {
            return null;
        } finally {
            $this->filesystem->remove($frame);
        }

        return null;
    }
}
