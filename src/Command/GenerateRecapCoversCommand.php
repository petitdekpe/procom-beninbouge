<?php

namespace App\Command;

use App\Repository\RecapRepository;
use App\Service\RecapCoverResolver;
use App\Service\VideoFrameExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recap:generate-covers',
    description: 'Génère la couverture des récaps qui n\'en ont pas (image extraite des vidéos téléversées, miniature YouTube).',
)]
final class GenerateRecapCoversCommand extends Command
{
    public function __construct(
        private RecapRepository $recapRepository,
        private RecapCoverResolver $coverResolver,
        private VideoFrameExtractor $frameExtractor,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Régénère aussi les couvertures déjà renseignées (même celles choisies à la main).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $updated = 0;
        $skipped = 0;
        $needsBrowser = [];

        foreach ($this->recapRepository->findAll() as $recap) {
            if ($this->coverResolver->apply($recap, $force)) {
                ++$updated;
                $io->writeln(\sprintf('  ✔ %s → %s', $recap->getSlug(), $recap->getCoverImage() ?? '(aucune)'));
            } elseif (!$recap->getCoverImage()) {
                $needsBrowser[] = $recap->getSlug();
            } else {
                ++$skipped;
            }
        }

        $this->entityManager->flush();

        $io->success(\sprintf('%d couverture(s) générée(s), %d déjà en place.', $updated, $skipped));

        if ([] !== $needsBrowser) {
            $io->warning(\sprintf(
                "%d récap(s) sans couverture : %s.\n%s",
                \count($needsBrowser),
                implode(', ', $needsBrowser),
                $this->frameExtractor->isAvailable()
                    ? 'ffmpeg n\'a pas pu lire leur vidéo : ouvrez-les dans l\'admin et utilisez « Générer la couverture depuis la vidéo ».'
                    : 'ffmpeg est introuvable (réglez FFMPEG_BINARY dans .env) : ouvrez-les dans l\'admin et utilisez « Générer la couverture depuis la vidéo ».',
            ));
        }

        return Command::SUCCESS;
    }
}
