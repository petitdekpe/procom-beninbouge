<?php

namespace App\Security\Mobile;

use App\Repository\MobileTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Vérifie l'en-tête « Authorization: Bearer … » de l'application mobile
 * (pare-feu « mobile » dans security.yaml).
 */
final class MobileTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private MobileTokenRepository $tokenRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokenRepository->findOneByPlainToken($accessToken);
        if (null === $token || !$token->isUsable()) {
            throw new BadCredentialsException('Session expirée.');
        }

        if ($token->touch()) {
            $this->entityManager->flush();
        }

        return new UserBadge($token->getUser()->getUserIdentifier());
    }
}
