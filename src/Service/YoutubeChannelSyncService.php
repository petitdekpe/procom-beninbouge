<?php

namespace App\Service;

use App\Entity\Enum\MediaType;
use App\Entity\Media;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports the latest videos from a YouTube channel's public RSS feed as
 * Media entities (type Video, no article attached — see Media::$article).
 * Videos already imported are skipped by matching Media::$youtubeVideoId,
 * so running the sync repeatedly is safe — except that an already-imported
 * video with no description gets it filled in from the feed (videos
 * imported before descriptions were synced, or edited on YouTube later).
 *
 * Deliberately uses the free, key-less RSS feed rather than the YouTube
 * Data API: no quota to manage, no credentials to provision, and "latest
 * videos from a channel" is exactly what it's built for. The trade-off is
 * that the feed only ever exposes the ~15 most recent uploads.
 */
final class YoutubeChannelSyncService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private MediaRepository $mediaRepository,
        #[Autowire(env: 'YOUTUBE_CHANNEL_HANDLE')]
        private string $channelHandle,
        #[Autowire(env: 'YOUTUBE_CHANNEL_ID')]
        private string $channelId = '',
    ) {
    }

    /**
     * @return int number of newly imported videos
     */
    public function sync(): int
    {
        $channelId = '' !== $this->channelId ? $this->channelId : $this->resolveChannelId($this->channelHandle);

        $feed = $this->httpClient->request('GET', "https://www.youtube.com/feeds/videos.xml?channel_id={$channelId}")->getContent();

        $xml = new \SimpleXMLElement($feed);
        $entries = $xml->children('http://www.w3.org/2005/Atom')->entry;

        $imported = 0;
        $changed = false;

        foreach ($entries as $entry) {
            $ytChildren = $entry->children('http://www.youtube.com/xml/schemas/2015');
            $videoId = (string) $ytChildren->videoId;

            if ('' === $videoId) {
                continue;
            }

            $mediaChildren = $entry->children('http://search.yahoo.com/mrss/');
            $description = trim((string) ($mediaChildren->group?->description ?? ''));

            $existing = $this->mediaRepository->findOneBy(['youtubeVideoId' => $videoId]);
            if (null !== $existing) {
                if ('' !== $description && '' === trim((string) $existing->getDescription())) {
                    $existing->setDescription($description);
                    $changed = true;
                }

                continue;
            }

            $atomChildren = $entry->children('http://www.w3.org/2005/Atom');
            $thumbnailUrl = (string) ($mediaChildren->group?->thumbnail?->attributes()->url ?? '');
            $publishedAt = new \DateTimeImmutable((string) $atomChildren->published);

            $video = (new Media())
                ->setType(MediaType::Video)
                ->setTitle((string) $atomChildren->title)
                ->setDescription('' !== $description ? $description : null)
                ->setYoutubeEmbedUrl("https://www.youtube.com/embed/{$videoId}")
                ->setYoutubeVideoId($videoId)
                ->setThumbnail('' !== $thumbnailUrl ? $thumbnailUrl : null)
                ->setCreatedAt($publishedAt);

            $this->entityManager->persist($video);
            ++$imported;
            $changed = true;
        }

        if ($changed) {
            $this->entityManager->flush();
        }

        return $imported;
    }

    /**
     * Resolves a "@handle" to its underlying "UC..." channel ID by reading
     * it off the channel's public page — the RSS feed only accepts the
     * raw ID. Set YOUTUBE_CHANNEL_ID directly to skip this lookup.
     */
    private function resolveChannelId(string $handle): string
    {
        $handle = ltrim($handle, '@');
        $page = $this->httpClient->request('GET', "https://www.youtube.com/@{$handle}")->getContent();

        // The canonical <link> is the most stable source (present on every
        // channel page); the embedded JSON blob is a fallback in case
        // YouTube ever drops it.
        if (1 === preg_match('#<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[0-9A-Za-z_-]{22})"#', $page, $matches)
            || 1 === preg_match('/"channelId":"(UC[0-9A-Za-z_-]{22})"/', $page, $matches)) {
            return $matches[1];
        }

        throw new \RuntimeException("Impossible de déterminer l'ID de la chaîne YouTube « @{$handle} ». Définissez YOUTUBE_CHANNEL_ID directement dans .env.local pour éviter cette résolution automatique.");
    }
}
