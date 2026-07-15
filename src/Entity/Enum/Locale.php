<?php

namespace App\Entity\Enum;

/**
 * Supported content locales. "fr" is the platform's canonical language
 * and lives directly on Article; ArticleTranslation provides the "fon"
 * (Fongbé) and "en" variants (and "fr" too, should a separate editorial
 * pass on the French copy ever be needed).
 */
enum Locale: string
{
    case Fr = 'fr';
    case Fon = 'fon';
    case En = 'en';
}
