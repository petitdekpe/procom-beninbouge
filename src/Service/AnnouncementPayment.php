<?php

namespace App\Service;

use App\Entity\Announcement;
use App\Entity\Enum\AnnouncementStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * FedaPay side of the announcement flow: opens the transaction (checkout
 * URL) and turns a FedaPay transaction status into an announcement status.
 * The status can arrive two ways, both routed through applyFedaPayStatus():
 * the customer coming back to the callback page (confirmed by reading the
 * transaction from FedaPay, see refresh()) and the signed webhook
 * (App\Controller\FedaPayWebhookController). Both are idempotent, so
 * whichever arrives second is a no-op.
 */
final class AnnouncementPayment
{
    public function __construct(
        private FedaPayClient $fedaPay,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return string the FedaPay checkout URL to redirect the advertiser to
     *
     * @throws \RuntimeException when FedaPay can't be reached or answers unexpectedly
     */
    public function initiate(Announcement $announcement, string $callbackUrl): string
    {
        $transaction = $this->fedaPay->createTransaction(
            $announcement->getAmount(),
            $announcement->getCurrency(),
            sprintf('Annonce %s | %s', $announcement->getReference(), mb_strimwidth((string) $announcement->getTitle(), 0, 80, '…')),
            array_filter([
                'firstname' => $announcement->getContactName(),
                'email' => $announcement->getContactEmail(),
            ]),
            $callbackUrl,
        );

        $transactionId = self::unwrap($transaction)['id'] ?? null;
        if (null === $transactionId) {
            throw new \RuntimeException('Réponse FedaPay inattendue : identifiant de transaction manquant.');
        }

        $announcement->setTransactionReference((string) $transactionId);
        $this->entityManager->flush();

        $token = $this->fedaPay->generateToken((int) $transactionId);
        $url = $token['url'] ?? $token['token']['url'] ?? null;
        if (!\is_string($url) || '' === $url) {
            throw new \RuntimeException('Réponse FedaPay inattendue : lien de paiement manquant.');
        }

        return $url;
    }

    /**
     * Re-reads the transaction from FedaPay and applies its status. Used on
     * the callback page, where the query string can't be trusted.
     */
    public function refresh(Announcement $announcement): void
    {
        if (null === $announcement->getTransactionReference() || AnnouncementStatus::PendingPayment !== $announcement->getStatus()) {
            return;
        }

        $transaction = self::unwrap($this->fedaPay->getTransaction((int) $announcement->getTransactionReference()));
        $this->applyFedaPayStatus($announcement, $transaction['status'] ?? null);
    }

    public function applyFedaPayStatus(Announcement $announcement, ?string $fedaPayStatus): void
    {
        match ($fedaPayStatus) {
            'approved', 'transferred' => $announcement->markPaid(new \DateTimeImmutable()),
            'declined', 'canceled' => $announcement->markPaymentFailed(),
            default => null,
        };

        $this->entityManager->flush();
    }

    /**
     * FedaPay wraps single resources as {"v1/transaction": {...}}.
     *
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private static function unwrap(array $response): array
    {
        return $response['v1/transaction'] ?? $response;
    }
}
