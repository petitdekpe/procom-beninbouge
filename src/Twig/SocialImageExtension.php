<?php

namespace App\Twig;

use App\Service\SocialImageGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * social_image(url) : version JPEG 1200 × 630 d'une image locale pour
 * og:image / twitter:image (voir App\Service\SocialImageGenerator).
 * Renvoie { url, width, height } ou null quand l'image d'origine doit être
 * utilisée telle quelle (URL externe, fichier absent…).
 */
final class SocialImageExtension extends AbstractExtension
{
    public function __construct(private readonly SocialImageGenerator $generator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('social_image', [$this, 'socialImage']),
        ];
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    public function socialImage(?string $url): ?array
    {
        $path = $this->generator->forPath($url);

        return null === $path ? null : [
            'url' => $path,
            'width' => SocialImageGenerator::WIDTH,
            'height' => SocialImageGenerator::HEIGHT,
        ];
    }
}
