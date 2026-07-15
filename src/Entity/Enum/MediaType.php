<?php

namespace App\Entity\Enum;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Pdf = 'pdf';
    case Infographic = 'infographic';
}
