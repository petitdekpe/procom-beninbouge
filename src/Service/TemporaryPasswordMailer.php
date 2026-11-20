<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\SiteSettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Donne à un compte un mot de passe temporaire aléatoire, qu'il doit changer
 * à sa première connexion (User::$mustChangePassword), et l'envoie par
 * e-mail. Le mot de passe n'est jamais affiché à l'administrateur, sauf en
 * développement quand aucun serveur d'envoi n'est configuré (voir
 * $deliveryConfigured), pour pouvoir tester.
 *
 * L'envoi est direct (pas de file Messenger) : l'administrateur voit tout de
 * suite si l'e-mail est parti, sans dépendre d'un worker qui consomme la file.
 */
final class TemporaryPasswordMailer
{
    // Sans 0/O, 1/l/I : le mot de passe se recopie sans ambiguïté.
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 12;

    public function __construct(
        private TransportInterface $transport,
        private UserPasswordHasherInterface $hasher,
        private SiteSettingsRepository $siteSettingsRepository,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        #[Autowire(env: 'MAILER_DSN')]
        private string $mailerDsn,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    /**
     * Remplace le mot de passe de $user (à enregistrer par l'appelant) et
     * envoie l'e-mail.
     *
     * @return array{sent: bool, error: ?string, devPassword: ?string}
     *                                                                 devPassword n'est renseigné qu'en développement sans serveur d'envoi
     */
    public function issue(User $user, bool $newAccount): array
    {
        $password = $this->generate();
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setMustChangePassword(true);

        $settings = $this->siteSettingsRepository->getSettings();
        $siteName = $settings->getSiteName() ?: 'Bénin Bouge';
        $fromAddress = $settings->getEmailFromAddress() ?: 'no-reply@beninbouge.com';
        $fromName = $settings->getEmailFromName() ?: $siteName;

        $context = [
            'user' => $user,
            'password' => $password,
            'siteName' => $siteName,
            'loginUrl' => $this->urlGenerator->generate('admin_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'newAccount' => $newAccount,
        ];

        $email = (new Email())
            ->from(new Address($fromAddress, $fromName))
            ->to(new Address((string) $user->getEmail(), (string) $user->getDisplayName()))
            ->subject($newAccount ? sprintf('Votre accès à l\'administration de %s', $siteName) : sprintf('Nouveau mot de passe temporaire pour %s', $siteName))
            ->html($this->twig->render('email/temporary_password.html.twig', $context))
            ->text($this->twig->render('email/temporary_password.txt.twig', $context));

        try {
            $this->transport->send($email);
        } catch (TransportExceptionInterface $e) {
            return ['sent' => false, 'error' => $e->getMessage(), 'devPassword' => null];
        }

        $deliveryConfigured = !str_starts_with($this->mailerDsn, 'null:');

        return [
            // Avec le transport « null », rien n'est réellement délivré.
            'sent' => $deliveryConfigured,
            'error' => $deliveryConfigured ? null : 'Aucun serveur d\'envoi d\'e-mails n\'est configuré (MAILER_DSN).',
            'devPassword' => !$deliveryConfigured && $this->debug ? $password : null,
        ];
    }

    private function generate(): string
    {
        $max = \strlen(self::ALPHABET) - 1;
        do {
            $password = '';
            for ($i = 0; $i < self::LENGTH; ++$i) {
                $password .= self::ALPHABET[random_int(0, $max)];
            }
        } while (1 !== preg_match('/[a-z]/', $password) || 1 !== preg_match('/[A-Z]/', $password) || 1 !== preg_match('/\d/', $password));

        return $password;
    }
}
