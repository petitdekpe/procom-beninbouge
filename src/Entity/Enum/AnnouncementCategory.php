<?php

namespace App\Entity\Enum;

enum AnnouncementCategory: string
{
    case Job = 'emploi';
    case Tender = 'appel-offres';
    case Training = 'bourse-formation';
    case Event = 'evenement';
    case Other = 'annonce';

    public function label(): string
    {
        return match ($this) {
            self::Job => 'Emploi & stage',
            self::Tender => "Appel d'offres",
            self::Training => 'Bourse & formation',
            self::Event => 'Événement',
            self::Other => 'Annonce',
        };
    }
}
