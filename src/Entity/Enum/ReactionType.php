<?php

namespace App\Entity\Enum;

enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Useful = 'useful';
    case Angry = 'angry';
}
