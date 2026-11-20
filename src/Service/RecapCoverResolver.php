<?php

namespace App\Service;

use App\Entity\Recap;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Détecte la couverture d'un récap à partir de sa vidéo :
 *  - YouTube : la miniature « maxresdefault » (1280 px) quand YouTube la
 *    fournit, sinon « hqdefault », toujours présente ;
 *  - fichier téléversé (MP4/WebM) : une image extraite de la vidéo par
 *    ffmpeg (App\Service\VideoFrameExtractor), si ffmpeg est disponible.
 *    Sinon l'image est générée dans le navigateur de l'admin au
 *    téléversement, ou à la demande avec le bouton « Générer la
 *    couverture » du formulaire.
 *
 * Une couverture n'est modifiée que si elle est vide ou si elle vient déjà
 * de YouTube (changement de vidéo) : une image choisie à la main n'est
 * jamais remplacée, sauf avec $force (commande app:recap:generate-covers).
 */
final class RecapCoverResolver
{
    private const YOUTUBE_THUMBNAIL_PREFIX = 'https://img.youtube.com/vi/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private VideoFrameExtractor $frameExtractor,
    ) {
    }

    /**
     * @return bool whether the cover was changed
     */
    public function apply(Recap $recap, bool $force = false): bool
    {
        $current = $recap->getCoverImage();
        $isAuto = !$current || str_starts_with($current, self::YOUTUBE_THUMBNAIL_PREFIX);

        if (!$isAuto && !$force) {
            return false;
        }

        $detected = $this->detect($recap);

        // Rien de détecté (ffmpeg absent, par exemple) : on garde l'existant.
        if (null === $detected && $current && !str_starts_with($current, self::YOUTUBE_THUMBNAIL_PREFIX)) {
            return false;
        }

        $recap->setCoverImage($detected);

        return $detected !== $current;
    }

    private function detect(Recap $recap): ?string
    {
        $id = $recap->getVideoYoutubeId();
        if ($id) {
            return $this->youtubeThumbnail($id);
        }

        $file = $recap->getVideoFileUrl();
        if ($file) {
            return $this->frameExtractor->extractCover($file);
        }

        return null;
    }

    private function youtubeThumbnail(string $id): string
    {
        $best = self::YOUTUBE_THUMBNAIL_PREFIX.$id.'/maxresdefault.jpg';

        try {
            if (200 === $this->httpClient->request('HEAD', $best, ['timeout' => 5])->getStatusCode()) {
                return $best;
            }
        } catch (\Throwable) {
            // Réseau indisponible ou miniature absente : on se rabat sur hqdefault.
        }

        return self::YOUTUBE_THUMBNAIL_PREFIX.$id.'/hqdefault.jpg';
    }
}
