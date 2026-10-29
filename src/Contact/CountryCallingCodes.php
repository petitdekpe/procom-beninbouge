<?php

namespace App\Contact;

/**
 * Indicatifs proposés dans le formulaire « Nous joindre »
 * (templates/partials/home_sections/_contact.html.twig) et acceptés par
 * App\Controller\ContactController — Bénin en tête (valeur par défaut),
 * puis la sous-région et les pays de la diaspora, par ordre alphabétique.
 */
final class CountryCallingCodes
{
    public const DEFAULT = '+229';

    /** @var array<string, string> indicatif => pays */
    public const LIST = [
        '+229' => 'Bénin',
        '+49' => 'Allemagne',
        '+32' => 'Belgique',
        '+226' => 'Burkina Faso',
        '+237' => 'Cameroun',
        '+1' => 'Canada / États-Unis',
        '+225' => "Côte d'Ivoire",
        '+33' => 'France',
        '+241' => 'Gabon',
        '+233' => 'Ghana',
        '+224' => 'Guinée',
        '+39' => 'Italie',
        '+223' => 'Mali',
        '+212' => 'Maroc',
        '+227' => 'Niger',
        '+234' => 'Nigeria',
        '+242' => 'République du Congo',
        '+243' => 'RD Congo',
        '+44' => 'Royaume-Uni',
        '+221' => 'Sénégal',
        '+41' => 'Suisse',
        '+235' => 'Tchad',
        '+228' => 'Togo',
    ];

    public static function isValid(string $code): bool
    {
        return \array_key_exists($code, self::LIST);
    }
}
