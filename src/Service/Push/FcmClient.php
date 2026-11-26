<?php

namespace App\Service\Push;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoi des notifications par Firebase Cloud Messaging (API HTTP v1).
 *
 * Réglage : FCM_SERVICE_ACCOUNT_FILE, chemin du fichier JSON du compte de
 * service Firebase, à garder hors de public/ et hors du dépôt. Sans lui,
 * les notifications sont simplement désactivées.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        #[Autowire('%env(default::FCM_SERVICE_ACCOUNT_FILE)%')]
        private ?string $serviceAccountFile = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->serviceAccountFile && '' !== $this->serviceAccountFile && is_readable($this->serviceAccountFile);
    }

    /**
     * @param array<string, string> $data
     *
     * @return bool faux si le téléphone n'existe plus : son jeton est à oublier
     */
    public function send(string $deviceToken, string $title, string $body, array $data): bool
    {
        if (!$this->isConfigured()) {
            return true;
        }

        try {
            $account = json_decode((string) file_get_contents((string) $this->serviceAccountFile), true, flags: \JSON_THROW_ON_ERROR);
            $response = $this->httpClient->request('POST', sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode($account['project_id'])), [
                'auth_bearer' => $this->accessToken($account),
                'timeout' => 5,
                'json' => ['message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $data,
                    'android' => ['priority' => 'HIGH'],
                ]],
            ]);

            $status = $response->getStatusCode();
            if (404 === $status) {
                return false;
            }
            if ($status >= 300) {
                $this->logger->warning('Notification refusée par Firebase.', ['status' => $status, 'body' => $response->getContent(false)]);
            }
        } catch (\Throwable $e) {
            // Une notification ratée ne doit jamais bloquer le travail de la rédaction.
            $this->logger->warning('Envoi de notification impossible.', ['exception' => $e]);
        }

        return true;
    }

    /** @param array<string, string> $account */
    private function accessToken(array $account): string
    {
        $item = $this->cache->getItem('fcm_access_token.'.hash('sha256', $account['client_email']));
        if ($item->isHit()) {
            return (string) $item->get();
        }

        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $now = time();
        $unsigned = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode((string) json_encode([
            'iss' => $account['client_email'],
            'scope' => self::SCOPE,
            'aud' => $account['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        if (!openssl_sign($unsigned, $signature, $account['private_key'], \OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Clé du compte de service Firebase illisible.');
        }

        $result = $this->httpClient->request('POST', $account['token_uri'], [
            'timeout' => 5,
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ],
        ])->toArray();

        $this->cache->save($item->set($result['access_token'])->expiresAfter(max(60, (int) $result['expires_in'] - 120)));

        return $result['access_token'];
    }
}
