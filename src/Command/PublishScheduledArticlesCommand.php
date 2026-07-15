<?php

namespace App\Command;

use App\Entity\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\ArticleSaver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Completes the "planification de publication" workflow: an editor sets an
 * article's status to Scheduled with a future publishedAt (already
 * possible from the admin form — see App\Form\ArticleAdminType). Nothing
 * automatically promoted it to Published once that date arrived, so it
 * never actually appeared on the site. This command is that missing step —
 * meant to be run on a schedule (cron / Windows Task Scheduler), frequently
 * enough (e.g. every 5 minutes) that publication feels close to on-time.
 */
#[AsCommand(
    name: 'app:publish-scheduled-articles',
    description: 'Publie automatiquement les articles programmés (Scheduled) dont la date de publication est passée',
)]
final class PublishScheduledArticlesCommand extends Command
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleSaver $articleSaver,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dueArticles = $this->articleRepository->findDueForScheduledPublication();

        if ([] === $dueArticles) {
            $io->writeln('Aucun article programmé à publier.');

            return Command::SUCCESS;
        }

        foreach ($dueArticles as $article) {
            $article->setStatus(ArticleStatus::Published);
        }

        $this->entityManager->flush();

        foreach ($dueArticles as $article) {
            $this->articleSaver->afterSave($article, wasPublished: false);
            $io->writeln(sprintf('Publié : « %s » (id=%d).', $article->getTitle(), $article->getId()));
        }

        $io->success(sprintf('%d article(s) publié(s).', \count($dueArticles)));

        return Command::SUCCESS;
    }
}
