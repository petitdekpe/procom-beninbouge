<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates a ROLE_ADMIN user. Deliberately CLI-only: there is no HTTP
 * endpoint capable of self-granting ROLE_ADMIN, and this command has no
 * --password option, so the secret can never end up in shell history,
 * process listings, or application logs — it is only ever typed into a
 * masked interactive prompt.
 */
#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Crée un utilisateur avec le rôle ROLE_ADMIN (mot de passe saisi de façon interactive et masquée)',
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, "Adresse e-mail du compte (demandée si absente)")
            ->addOption('display-name', null, InputOption::VALUE_REQUIRED, 'Nom affiché du compte (demandé si absent)')
            ->setHelp(<<<'HELP'
                Crée un utilisateur avec le rôle <comment>ROLE_ADMIN</comment>.

                Pour des raisons de sécurité, le mot de passe est <comment>toujours</comment> saisi de
                façon interactive et masquée : il n'existe volontairement aucune option --password,
                afin qu'il ne puisse jamais se retrouver en clair dans l'historique du shell, dans la
                liste des processus ou dans des logs.

                  <info>php bin/console app:user:create-admin</info>
                  <info>php bin/console app:user:create-admin --email=admin@example.com</info>
                HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création d\'un compte administrateur');

        if (!$input->isInteractive()) {
            $io->error('Cette commande nécessite un terminal interactif (saisie masquée du mot de passe) et ne peut pas être exécutée avec --no-interaction.');

            return Command::FAILURE;
        }

        $email = $input->getOption('email') ?: $io->ask('Adresse e-mail', null, function (?string $value): string {
            if (!$value || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Adresse e-mail invalide.');
            }

            return $value;
        });

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un compte existe déjà avec l\'adresse « %s ».', $email));

            return Command::FAILURE;
        }

        $displayName = $input->getOption('display-name') ?: $io->ask('Nom affiché', null, function (?string $value): string {
            if (!$value || '' === trim($value)) {
                throw new \RuntimeException('Le nom affiché est obligatoire.');
            }

            return $value;
        });

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $passwordQuestion = new Question('Mot de passe (8 caractères minimum)');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setMaxAttempts(3);
        $passwordQuestion->setValidator(function (?string $value): string {
            if (!$value || mb_strlen($value) < 8) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
            }

            return $value;
        });

        try {
            $password = $questionHelper->ask($input, $output, $passwordQuestion);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $confirmQuestion = new Question('Confirmez le mot de passe');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setMaxAttempts(1);
        $confirmation = $questionHelper->ask($input, $output, $confirmQuestion);

        if ($password !== $confirmation) {
            $io->error('Les deux saisies du mot de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error(sprintf('%s : %s', $violation->getPropertyPath(), $violation->getMessage()));
            }

            return Command::FAILURE;
        }

        if (!$io->confirm(sprintf('Créer le compte ROLE_ADMIN « %s » ?', $email), false)) {
            $io->warning('Création annulée.');

            return Command::SUCCESS;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Compte administrateur créé : %s (id=%d).', $email, $user->getId()));

        return Command::SUCCESS;
    }
}
