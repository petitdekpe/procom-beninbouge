<?php

namespace App\Service\Mobile;

/**
 * Le HTML que l'éditeur du site (Quill, repris dans l'application) sait
 * produire. Deux usages :
 * - refuser qu'un contenu envoyé par l'application contienne autre chose
 *   (script, attribut d'événement, lien « javascript: »…) ;
 * - signaler à l'application qu'un article existant contient des éléments
 *   que son éditeur effacerait : le texte y reste alors en lecture seule.
 */
final class HtmlContentPolicy
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'a', 'img', 'span'];

    private const ATTRIBUTES = [
        'a' => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt'],
        'li' => ['data-list'],
        'span' => ['contenteditable'],
    ];

    public function isSupported(?string $html): bool
    {
        if (null === $html || '' === trim($html)) {
            return true;
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8"?><div id="bb-root">'.$html.'</div>', \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return false;
        }

        // Tout ce que l'analyseur a placé hors du conteneur (<style>, <meta>…) est refusé.
        foreach ($document->getElementsByTagName('*') as $element) {
            $tag = strtolower($element->nodeName);
            if (\in_array($tag, ['html', 'head', 'body'], true) && 0 === $element->attributes->length) {
                continue;
            }
            if ('div' === $tag && 'bb-root' === $element->getAttribute('id')) {
                continue;
            }
            if (!\in_array($tag, self::TAGS, true)) {
                return false;
            }

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = (string) $attribute->nodeValue;

                if ('class' === $name) {
                    if ('' !== trim($value) && 1 !== preg_match('/^ql-[a-z0-9-]+(?:\s+ql-[a-z0-9-]+)*$/', trim($value))) {
                        return false;
                    }
                    continue;
                }
                if (!\in_array($name, self::ATTRIBUTES[$tag] ?? [], true)) {
                    return false;
                }
                if ('href' === $name && !$this->isSafeUrl($value, ['http', 'https', 'mailto', 'tel'])) {
                    return false;
                }
                if ('src' === $name && !$this->isSafeUrl($value, ['http', 'https'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<string> $schemes
     */
    private function isSafeUrl(string $url, array $schemes): bool
    {
        // Les navigateurs ignorent espaces et caractères de contrôle dans le schéma.
        $url = (string) preg_replace('/[\x00-\x20]+/', '', $url);

        if (1 !== preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $matches)) {
            return true;
        }

        return \in_array(strtolower($matches[1]), $schemes, true);
    }
}
