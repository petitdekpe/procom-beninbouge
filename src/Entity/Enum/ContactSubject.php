<?php

namespace App\Entity\Enum;

enum ContactSubject: string
{
    case Information = 'renseignement';
    case Suggestion = 'suggestion';
    case Partnership = 'partenariat';

    public function label(): string
    {
        return match ($this) {
            self::Information => 'Renseignement',
            self::Suggestion => 'Suggestion',
            self::Partnership => 'Devenir partenaire',
        };
    }
}
