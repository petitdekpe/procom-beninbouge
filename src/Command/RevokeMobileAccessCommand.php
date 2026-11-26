<?php

namespace App\Command;

use App\Repository\MobileTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mobile:revoke', description: 'Déconnecte tous les téléphones d\'un compte (appareil perdu, départ).')]
final class RevokeMobileAccessCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private MobileTokenRepository $tokenRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'E-mail du compte');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->userRepository->findOneBy(['email' => $input->getArgument('email')]);
        if (null === $user) {
            $io->error('Aucun compte avec cet e-mail.');

            return Command::FAILURE;
        }

        $tokens = $this->tokenRepository->findOfUser($user);
        foreach ($tokens as $token) {
            $this->entityManager->remove($token);
        }
        $this->entityManager->flush();
        $io->success(sprintf('%d accès mobile(s) supprimé(s).', \count($tokens)));

        return Command::SUCCESS;
    }
}
