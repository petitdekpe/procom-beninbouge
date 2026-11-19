<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * rich_text : affiche un texte saisi soit en texte brut (dépôt public d'une
 * annonce, anciennes annonces), soit en HTML par l'éditeur riche de l'admin.
 * Le texte brut est échappé avec ses retours à la ligne ; le HTML passe par
 * le sanitizer app.rich_text_sanitizer (config/packages/html_sanitizer.yaml),
 * qui ne garde que la mise en forme. Jamais de |raw sur ces contenus.
 */
final class RichTextExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.app.rich_text_sanitizer')]
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('rich_text', [$this, 'richText'], ['is_safe' => ['html']]),
        ];
    }

    public function richText(?string $text): string
    {
        $text = (string) $text;

        if (!preg_match('/<[a-z][^>]*>/i', $text)) {
            return nl2br(htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), false);
        }

        return $this->sanitizer->sanitize($text);
    }
}
