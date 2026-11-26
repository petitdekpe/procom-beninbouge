<?php

namespace App\Controller\Api\Mobile;

use App\Entity\MobileToken;
use App\Entity\User;
use App\Repository\MobileTokenRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Service\EditorProfileProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Connexion de l'application, profil, appareils connectés et jeton de
 * notification.
 */
#[Route('/api/mobile/v1')]
final class SessionController extends AbstractMobileController
{
    private const MAX_FAILURES = 5;
    private const LOCK_SECONDS = 900;

    public function __construct(
        private MobileTokenRepository $tokenRepository,
        private EntityManagerInterface $entityManager,
        private PermissionChecker $permissionChecker,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    #[Route('/login', name: 'mobile_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        EditorProfileProvisioner $profiles,
    ): JsonResponse {
        $data = $this->payload($request);
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $device = trim((string) ($data['device'] ?? '')) ?: 'Téléphone Android';

        // Frein aux essais de mots de passe : par compte et par adresse IP.
        $throttle = $this->cache->getItem('mobile_login.'.hash('sha256', mb_strtolower($email).'|'.$request->getClientIp()));
        $failures = (int) $throttle->get();
        if ($failures >= self::MAX_FAILURES) {
            return $this->error('Trop de tentatives. Réessayez dans 15 minutes.', 429);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User || !$hasher->isPasswordValid($user, $password)) {
            $this->cache->save($throttle->set($failures + 1)->expiresAfter(self::LOCK_SECONDS));

            return $this->error('E-mail ou mot de passe incorrect.', 401);
        }
        $this->cache->deleteItem($throttle->getKey());

        if (!$user->isEnabled()) {
            return $this->error('Ce compte est désactivé. Contactez un administrateur.', 403);
        }
        if ($user->isMustChangePassword()) {
            return $this->error('Choisissez d\'abord votre propre mot de passe en vous connectant à l\'administration du site, puis revenez dans l\'application.', 403);
        }
        if ([] === $this->permissionChecker->permissionsOf($user)) {
            return $this->error('Ce compte n\'a accès à aucune fonction de la rédaction.', 403);
        }

        if ($this->permissionChecker->has($user, 'articles.write')) {
            $profiles->ensureProfile($user);
        }

        $plainToken = 'bbm_'.bin2hex(random_bytes(32));
        $this->entityManager->persist(new MobileToken($user, $plainToken, $device));
        $this->entityManager->flush();

        return $this->json(['token' => $plainToken, 'user' => $this->profile($user)]);
    }

    #[Route('/me', name: 'mobile_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json(['user' => $this->profile($this->currentUser())]);
    }

    #[Route('/logout', name: 'mobile_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $token = $this->currentToken($request);
        if (null !== $token) {
            $this->entityManager->remove($token);
            $this->entityManager->flush();
        }

        return $this->json(['ok' => true]);
    }

    /** Téléphones connectés au compte : permet de couper un appareil perdu. */
    #[Route('/devices', name: 'mobile_devices', methods: ['GET'])]
    public function devices(Request $request): JsonResponse
    {
        $current = $this->currentToken($request);

        return $this->json(['items' => array_values(array_map(fn (MobileToken $token) => [
            'id' => $token->getId(),
            'name' => $token->getDeviceName(),
            'lastUsedAt' => $this->date($token->getLastUsedAt()),
            'current' => $token === $current,
        ], array_filter($this->tokenRepository->findOfUser($this->currentUser()), static fn (MobileToken $t) => $t->isUsable())))]);
    }

    #[Route('/devices/{id}/revoke', name: 'mobile_device_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revokeDevice(int $id): JsonResponse
    {
        $token = $this->tokenRepository->findOneBy(['id' => $id, 'user' => $this->currentUser()])
            ?? throw $this->createNotFoundException();
        $this->entityManager->remove($token);
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/push-token', name: 'mobile_push_token', methods: ['POST'])]
    public function pushToken(Request $request): JsonResponse
    {
        $pushToken = trim((string) ($this->payload($request)['pushToken'] ?? ''));
        $current = $this->currentToken($request);
        if (null === $current || '' === $pushToken || \strlen($pushToken) > 255) {
            return $this->error('Jeton de notification invalide.', 400);
        }

        // Le téléphone a peut-être changé de compte : un seul accès le garde.
        foreach ($this->tokenRepository->findBy(['pushToken' => $pushToken]) as $other) {
            $other->setPushToken(null);
        }
        $current->setPushToken($pushToken);
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }

    private function currentToken(Request $request): ?MobileToken
    {
        $header = (string) $request->headers->get('Authorization');

        return str_starts_with($header, 'Bearer ') ? $this->tokenRepository->findOneByPlainToken(substr($header, 7)) : null;
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'admin' => \in_array('ROLE_ADMIN', $user->getRoles(), true),
            'permissions' => $this->permissionChecker->permissionsOf($user),
        ];
    }
}
