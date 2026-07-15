<?php

namespace App\Entity\Enum;

enum SubmissionStatus: string
{
    case New = 'nouveau';
    case InProgress = 'en_cours';
    case Resolved = 'traite';
    case Rejected = 'rejete';
}
