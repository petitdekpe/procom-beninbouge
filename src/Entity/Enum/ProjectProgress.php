<?php

namespace App\Entity\Enum;

enum ProjectProgress: string
{
    case Planifie = 'planifie';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Suspendu = 'suspendu';
    case Abandonne = 'abandonne';
}
