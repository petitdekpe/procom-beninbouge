<?php

namespace App\Entity\Enum;

/**
 * Cycle de vie d'une annonce : créée en attente de paiement, payée puis
 * soumise à validation (48 h au plus), validée (en ligne pour la durée
 * achetée, à compter de la validation) ou refusée. Un paiement échoué ou
 * annulé la passe en PaymentFailed.
 */
enum AnnouncementStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentFailed = 'payment_failed';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'En attente de paiement',
            self::PaymentFailed => 'Paiement non abouti',
            self::PendingReview => 'En cours de validation',
            self::Approved => 'Validée',
            self::Rejected => 'Refusée',
        };
    }
}
