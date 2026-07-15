<?php

namespace App\Entity\Enum;

enum ActivityAction: string
{
    case Publication = 'publication';
    case Moderation = 'moderation';
    case Deletion = 'deletion';
    case Trashed = 'trashed';
    case Restored = 'restored';

    // Workflow éditorial (Journaliste → Rédacteur en chef → Correcteur) —
    // see App\Service\ArticleSaver.
    case SubmittedForReview = 'submitted_for_review';
    case ReviewApproved = 'review_approved';
    case ReviewRejected = 'review_rejected';
    case Validated = 'validated';
    case CorrectionRejected = 'correction_rejected';
    case Unpublished = 'unpublished';
    case Archived = 'archived';
    case Unarchived = 'unarchived';
}
