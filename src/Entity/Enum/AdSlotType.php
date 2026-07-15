<?php

namespace App\Entity\Enum;

enum AdSlotType: string
{
    case Banner = 'banner';
    case Native = 'native';
    case SponsoredContent = 'sponsored_content';
}
