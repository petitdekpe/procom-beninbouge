<?php

namespace App\Entity\Enum;

enum AdEventKind: string
{
    case Impression = 'impression';
    case Click = 'click';
}
