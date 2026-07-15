<?php

namespace App\Controller;

use App\Entity\Enum\DonationStatus;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Webhook FedaPay de confirmation de paiement. La charge utile ne
 * correspond pas à la forme de la ressource Donation (FedaPay envoie un
 * évènement avec une "entity" imbriquée) : c'est volontairement un
 * contrôleur Symfony classique plutôt qu'une opération API Platform.
 *
 * Référence FedaPay : l'en-tête de signature a la forme
 * "t=<timestamp>,s=<hmac>", où <hmac> = HMAC-SHA256("<timestamp>.<payload>")
 * calculé avec le secret de webhook.
 */
final class FedaPayWebhookController
{
    public function __construct(
        private DonationRepository $donationRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'FEDAPAY_WEBHOOK_SECRET')]
        private string $webhookSecret,
    ) {
    }

    #[Route('/api/donations/webhook', name: 'fedapay_donation_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signatureHeader = $request->headers->get('X-FEDAPAY-SIGNATURE', '');

        if (!$this->isSignatureValid($payload, $signatureHeader)) {
            return new JsonResponse(['error' => 'Signature invalide.'], Response::HTTP_UNAUTHORIZED);
        }

        $event = json_decode($payload, true);
        if (!\is_array($event)) {
            return new JsonResponse(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $entity = $event['entity'] ?? null;
        $transactionId = $entity['id'] ?? null;
        $fedaPayStatus = $entity['status'] ?? null;

        if (null === $transactionId) {
            return new JsonResponse(['error' => 'Identifiant de transaction manquant.'], Response::HTTP_BAD_REQUEST);
        }

        $donation = $this->donationRepository->findOneBy(['transactionReference' => (string) $transactionId]);

        if (null === $donation) {
            return new JsonResponse(['error' => 'Don introuvable pour cette transaction.'], Response::HTTP_NOT_FOUND);
        }

        $newStatus = match ($fedaPayStatus) {
            'approved' => DonationStatus::Completed,
            'declined' => DonationStatus::Failed,
            'canceled' => DonationStatus::Cancelled,
            default => null,
        };

        if (null !== $newStatus) {
            $donation->setStatus($newStatus);
            $this->entityManager->flush();
        }

        return new JsonResponse(['ok' => true]);
    }

    private function isSignatureValid(string $payload, string $signatureHeader): bool
    {
        if ('' === $this->webhookSecret || '' === $signatureHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $chunk) {
            [$key, $value] = array_pad(explode('=', $chunk, 2), 2, null);
            if (null !== $key && null !== $value) {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['s'] ?? null;

        if (null === $timestamp || null === $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
