<?php

namespace App\Command;

use App\Service\YoutubeChannelSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Meant to be run on a schedule (cron / Windows Task Scheduler) — see
 * App\Service\YoutubeChannelSyncService for how the sync itself works.
 */
#[AsCommand(
    name: 'app:sync-youtube-videos',
    description: 'Importe les dernières vidéos de la chaîne YouTube (YOUTUBE_CHANNEL_HANDLE) comme Media non rattachés à un article',
)]
final class SyncYoutubeVideosCommand extends Command
{
    public function __construct(
        private YoutubeChannelSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $imported = $this->syncService->sync();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (0 === $imported) {
            $io->writeln('Aucune nouvelle vidéo à importer.');
        } else {
            $io->success(sprintf('%d nouvelle(s) vidéo(s) importée(s).', $imported));
        }

        return Command::SUCCESS;
    }
}
