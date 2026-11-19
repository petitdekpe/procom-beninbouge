<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Image d'aperçu pour les partages sur les réseaux sociaux (og:image).
 *
 * Les couvertures sont stockées en WebP et souvent en portrait (1024 × 1200…),
 * alors que Facebook, LinkedIn ou WhatsApp attendent du JPEG au format
 * 1,91:1 : un WebP peut être ignoré par leur robot, qui se rabat sur une
 * autre image de la page (le logo). On fabrique donc, à la première demande,
 * un JPEG 1200 × 630 : la couverture entière, centrée, sur un fond flou tiré
 * d'elle-même, sans rien couper.
 *
 * Le fichier est mis en cache dans public/uploads/social, sous un nom qui
 * dépend du chemin et de la date de modification de la source : remplacer
 * la couverture produit un nouveau fichier, donc une nouvelle URL.
 * Seules les images locales (/uploads/…) sont traitées ; pour une URL
 * externe ou en cas d'échec, forPath() renvoie null et l'appelant garde
 * l'image d'origine.
 */
final class SocialImageGenerator
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    private const QUALITY = 85;
    private const PUBLIC_PREFIX = '/uploads/social/';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return string|null chemin public du JPEG 1200 × 630, ou null si l'image n'est pas traitable
     */
    public function forPath(?string $url): ?string
    {
        $path = $url ? parse_url($url, \PHP_URL_PATH) : null;
        if (!\is_string($path) || !str_starts_with($path, '/uploads/') || str_contains($path, '..') || str_starts_with($path, self::PUBLIC_PREFIX)) {
            return null;
        }

        $source = $this->publicDir.$path;
        if (!is_file($source)) {
            return null;
        }

        $filename = sha1($path.'|'.filemtime($source)).'.jpg';
        $target = $this->publicDir.self::PUBLIC_PREFIX.$filename;

        if (!is_file($target) && !$this->generate($source, $target)) {
            return null;
        }

        return self::PUBLIC_PREFIX.$filename;
    }

    private function generate(string $source, string $target): bool
    {
        if (!\function_exists('imagecreatefromstring')) {
            return false;
        }

        $data = @file_get_contents($source);
        $image = false !== $data ? @imagecreatefromstring($data) : false;
        if (false === $image) {
            $this->logger->warning('Image de partage : source illisible.', ['source' => $source]);

            return false;
        }

        imagepalettetotruecolor($image);
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 17, 27, 20));

        // Fond : la couverture recadrée au format 1,91:1, floutée en petite
        // taille (peu coûteux) puis agrandie en bicubique pour rester lisse.
        $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);
        $small = imagecreatetruecolor(120, 63);
        imagecopyresampled($small, $image, 0, 0, intdiv($sourceWidth - $cropWidth, 2), intdiv($sourceHeight - $cropHeight, 2), 120, 63, $cropWidth, $cropHeight);
        for ($i = 0; $i < 25; ++$i) {
            imagefilter($small, \IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagefilter($small, \IMG_FILTER_BRIGHTNESS, -45);
        $background = imagescale($small, self::WIDTH, self::HEIGHT, \IMG_BICUBIC);
        if (false !== $background) {
            imagecopy($canvas, $background, 0, 0, 0, 0, self::WIDTH, self::HEIGHT);
        }

        // Premier plan : la couverture entière, centrée.
        $fit = min(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
        $width = (int) round($sourceWidth * $fit);
        $height = (int) round($sourceHeight * $fit);
        imagecopyresampled($canvas, $image, intdiv(self::WIDTH - $width, 2), intdiv(self::HEIGHT - $height, 2), 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        $this->filesystem->mkdir(\dirname($target));
        $temporary = $target.'.'.bin2hex(random_bytes(4)).'.tmp';
        imageinterlace($canvas, true);
        $written = imagejpeg($canvas, $temporary, self::QUALITY);

        if (!$written) {
            $this->filesystem->remove($temporary);
            $this->logger->warning('Image de partage : écriture impossible.', ['target' => $target]);

            return false;
        }

        // Renommage atomique : deux robots qui arrivent en même temps ne
        // lisent jamais un fichier à moitié écrit.
        $this->filesystem->rename($temporary, $target, true);

        return true;
    }
}
