<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around the FedaPay REST API (https://docs.fedapay.com),
 * used to create a transaction then generate its checkout token/URL.
 * No FedaPay SDK is required: two plain HTTP calls cover the donation flow.
 */
final class FedaPayClient
{
    public function __construct(
        #[Autowire(env: 'FEDAPAY_SECRET_KEY')]
        private string $secretKey,
        #[Autowire(env: 'FEDAPAY_ENVIRONMENT')]
        private string $environment,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, mixed>
     */
    public function createTransaction(int $amount, string $currencyIso, string $description, array $customer): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl().'/transactions', [
            'auth_bearer' => $this->secretKey,
            'json' => array_filter([
                'description' => $description,
                'amount' => $amount,
                'currency' => ['iso' => $currencyIso],
                'customer' => $customer ?: null,
            ]),
        ]);

        return $response->toArray(false);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateToken(int $transactionId): array
    {
        $response = $this->httpClient->request('POST', sprintf('%s/transactions/%d/token', $this->baseUrl(), $transactionId), [
            'auth_bearer' => $this->secretKey,
        ]);

        return $response->toArray(false);
    }

    private function baseUrl(): string
    {
        return 'live' === $this->environment
            ? 'https://api.fedapay.com/v1'
            : 'https://sandbox-api.fedapay.com/v1';
    }
}
