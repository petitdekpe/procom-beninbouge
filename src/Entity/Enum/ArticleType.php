<?php

namespace App\Entity\Enum;

enum ArticleType: string
{
    case Editorial = 'editorial';
    case Sponsored = 'sponsored';
    /** Récap : article court centré sur une vidéo au format mobile (vertical). */
    case Recap = 'recap';
}
