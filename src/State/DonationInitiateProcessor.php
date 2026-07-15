<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Donation;
use App\Service\FedaPayClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Ouvre une transaction FedaPay pour le don, stocke sa référence et
 * renvoie l'URL de paiement à suivre par le donateur. La confirmation
 * définitive (paiement réussi/échoué) arrive plus tard via le webhook
 * FedaPay, pas dans cette réponse.
 *
 * @implements ProcessorInterface<Donation, Donation>
 */
final class DonationInitiateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private FedaPayClient $fedaPay,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Donation) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        try {
            $transaction = $this->fedaPay->createTransaction(
                $data->getAmount(),
                $data->getCurrency(),
                'Don à Bénin Bouge',
                array_filter([
                    'firstname' => $data->getDonorName(),
                    'email' => $data->getDonorEmail(),
                    'phone_number' => $data->getDonorPhone() ? [
                        'number' => $data->getDonorPhone(),
                        'country' => 'bj',
                    ] : null,
                ]),
            );

            $transactionId = $transaction['v1/transaction']['id'] ?? $transaction['id'] ?? null;

            if (null === $transactionId) {
                throw new \RuntimeException('Réponse FedaPay inattendue : identifiant de transaction manquant.');
            }

            $data->setTransactionReference((string) $transactionId);

            $token = $this->fedaPay->generateToken((int) $transactionId);
            $data->setPaymentUrl($token['url'] ?? $token['token']['url'] ?? null);
        } catch (\Throwable $e) {
            throw new ServiceUnavailableHttpException(
                null,
                sprintf('Le service de paiement FedaPay est momentanément indisponible : %s', $e->getMessage()),
            );
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
