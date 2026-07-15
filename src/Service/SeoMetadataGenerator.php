<?php

namespace App\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class SeoMetadataGenerator
{
    private readonly AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function slugify(string $text): string
    {
        return $this->slugger->slug($text)->lower()->toString();
    }

    /**
     * @param callable(string): bool $isTaken returns true if the candidate slug is already used
     */
    public function uniqueSlug(string $base, callable $isTaken): string
    {
        $slug = $this->slugify($base);
        $candidate = $slug;
        $suffix = 2;

        while ($isTaken($candidate)) {
            $candidate = sprintf('%s-%d', $slug, $suffix);
            ++$suffix;
        }

        return $candidate;
    }

    public function metaTitle(string $title, int $maxLength = 60): string
    {
        return $this->truncate(trim($title), $maxLength);
    }

    public function metaDescription(string $text, int $maxLength = 160): string
    {
        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));

        return $this->truncate($plain, $maxLength);
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 1)).'…';
    }
}
