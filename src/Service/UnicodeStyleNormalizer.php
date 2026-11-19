<?php

namespace App\Service;

/**
 * Faux gras et faux italiques Unicode (« 𝗔𝗡𝗚𝗘́𝗟𝗜𝗤𝗨𝗘 », « 𝐁𝐞𝐧𝐢𝐧 », « 𝘪𝘵𝘢𝘭𝘪𝘲𝘶𝘦 »)
 * collés depuis Facebook ou WhatsApp. Ce sont des symboles mathématiques
 * (bloc U+1D400–U+1D7FF), pas des lettres : ils échappent aux polices du
 * site, sont lus lettre par lettre par les lecteurs d'écran et ignorés par
 * la recherche et Google.
 *
 * toPlain() les ramène aux lettres et chiffres ordinaires (titres, meta) ;
 * toHtml() fait de même dans un contenu HTML mais garde l'intention de mise
 * en forme : les passages en gras deviennent <strong>, ceux en italique <em>.
 * Les accents combinés (« 𝗘́ ») sont recomposés en « É ».
 */
final class UnicodeStyleNormalizer
{
    private const LETTERS_START = 0x1D400;
    private const LETTERS_END = 0x1D6A3;
    private const DIGITS_START = 0x1D7CE;
    private const DIGITS_END = 0x1D7FF;

    /** Premier caractère (« A ») de chaque alphabet de 52 lettres, avec son style. */
    private const ALPHABETS = [
        0x1D400 => 'bold',        // gras
        0x1D434 => 'italic',      // italique
        0x1D468 => 'bold-italic', // gras italique
        0x1D49C => null,          // script
        0x1D4D0 => 'bold',        // script gras
        0x1D504 => null,          // fraktur
        0x1D538 => null,          // ajouré
        0x1D56C => 'bold',        // fraktur gras
        0x1D5A0 => null,          // sans empattement
        0x1D5D4 => 'bold',        // sans empattement gras (le plus courant sur Facebook)
        0x1D608 => 'italic',      // sans empattement italique
        0x1D63C => 'bold-italic', // sans empattement gras italique
        0x1D670 => null,          // chasse fixe
    ];

    /** Premier chiffre (« 0 ») de chaque série de 10, avec son style. */
    private const DIGIT_SETS = [
        0x1D7CE => 'bold',
        0x1D7D8 => null,
        0x1D7E2 => null,
        0x1D7EC => 'bold',
        0x1D7F6 => null,
    ];

    // Un passage stylé : commence et finit par un caractère stylé, et peut
    // contenir espaces, ponctuation et accents combinés entre les deux.
    private const RUN = '/[\x{1D400}-\x{1D7FF}](?:[\x{1D400}-\x{1D7FF}\x{0300}-\x{036F}\s\'’,.:;!?«»()&-]*[\x{1D400}-\x{1D7FF}\x{0300}-\x{036F}])?/u';

    public function hasStyledCharacters(?string $text): bool
    {
        return null !== $text && 1 === preg_match('/[\x{1D400}-\x{1D7FF}]/u', $text);
    }

    public function toPlain(?string $text): ?string
    {
        if (!$this->hasStyledCharacters($text)) {
            return $text;
        }

        $plain = (string) preg_replace_callback('/[\x{1D400}-\x{1D7FF}]/u', fn (array $m): string => $this->plainChar($m[0]), $text);

        return $this->compose($plain);
    }

    public function toHtml(?string $html): ?string
    {
        if (!$this->hasStyledCharacters($html)) {
            return $html;
        }

        $converted = (string) preg_replace_callback(self::RUN, function (array $m): string {
            $plain = (string) preg_replace_callback('/[\x{1D400}-\x{1D7FF}]/u', fn (array $c): string => $this->plainChar($c[0]), $m[0]);
            $plain = htmlspecialchars($this->compose($plain), \ENT_NOQUOTES | \ENT_HTML5, 'UTF-8', false);

            return match ($this->styleOf($m[0])) {
                'bold' => '<strong>'.$plain.'</strong>',
                'italic' => '<em>'.$plain.'</em>',
                'bold-italic' => '<strong><em>'.$plain.'</em></strong>',
                default => $plain,
            };
        }, $html);

        return $converted;
    }

    private function plainChar(string $char): string
    {
        $cp = mb_ord($char, 'UTF-8');

        if ($cp >= self::LETTERS_START && $cp <= self::LETTERS_END) {
            $offset = ($cp - self::LETTERS_START) % 52;

            return \chr($offset < 26 ? 0x41 + $offset : 0x61 + $offset - 26);
        }

        if ($cp >= self::DIGITS_START && $cp <= self::DIGITS_END) {
            return (string) (($cp - self::DIGITS_START) % 10);
        }

        // Lettres grecques stylées et autres : laissées telles quelles.
        return $char;
    }

    /** Style du premier caractère stylé d'un passage. */
    private function styleOf(string $run): ?string
    {
        preg_match('/[\x{1D400}-\x{1D7FF}]/u', $run, $m);
        $cp = mb_ord($m[0], 'UTF-8');

        $sets = $cp >= self::DIGITS_START ? self::DIGIT_SETS : self::ALPHABETS;
        $style = null;
        foreach ($sets as $start => $candidate) {
            if ($cp >= $start) {
                $style = $candidate;
            }
        }

        return $style;
    }

    /** Recompose « E + accent combiné » en « É » quand l'extension intl est là. */
    private function compose(string $text): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text) : $text;
    }
}
