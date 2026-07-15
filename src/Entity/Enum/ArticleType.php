<?php

namespace App\Entity\Enum;

enum ArticleType: string
{
    case Editorial = 'editorial';
    case Sponsored = 'sponsored';
}
