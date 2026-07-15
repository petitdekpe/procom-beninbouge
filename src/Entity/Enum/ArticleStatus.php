<?php

namespace App\Entity\Enum;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case InCorrection = 'in_correction';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
