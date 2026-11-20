<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Recap;
use App\Service\ArticleSaver;
use App\Service\UnicodeStyleNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rattrapage, à lancer une fois : nettoie les faux gras/italiques Unicode des
 * articles enregistrés avant que App\Service\ArticleSaver ne le fasse à
 * chaque enregistrement (lettres ordinaires dans le titre et les meta,
 * <strong>/<em> dans le contenu). Une révision est gardée pour chaque
 * article modifié, donc l'ancienne version reste restaurable depuis
 * l'historique. Les récaps (titre, meta, texte) sont traités de la même
 * façon, sans historique. --dry-run liste les contenus concernés sans rien
 * changer.
 */
#[AsCommand(
    name: 'app:normalize-unicode-styles',
    description: 'Remplace les faux gras/italiques Unicode des articles par du texte normal (titres) et une vraie mise en forme (contenu)',
)]
final class NormalizeUnicodeStylesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleSaver $articleSaver,
        private UnicodeStyleNormalizer $unicodeStyles,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lister les articles concernés sans les modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var list<Article> $articles */
        $articles = $this->entityManager->getRepository(Article::class)->findAll();
        $changed = [];

        foreach ($articles as $article) {
            $before = $article->getTitle();
            if ($dryRun) {
                $affected = $this->unicodeStyles->hasStyledCharacters($article->getTitle())
                    || $this->unicodeStyles->hasStyledCharacters($article->getMetaTitle())
                    || $this->unicodeStyles->hasStyledCharacters($article->getMetaDescription())
                    || $this->unicodeStyles->hasStyledCharacters($article->getContent());
            } else {
                $affected = $this->articleSaver->normalizeUnicodeStyles($article);
            }

            if ($affected) {
                $changed[] = $article;
                $io->writeln(sprintf('%s n°%d : %s → %s', $dryRun ? 'À corriger' : 'Corrigé', $article->getId(), $before, $dryRun ? $this->unicodeStyles->toPlain($before) : $article->getTitle()));
            }
        }

        /** @var list<Recap> $recaps */
        $recaps = $this->entityManager->getRepository(Recap::class)->findAll();
        $changedRecaps = [];

        foreach ($recaps as $recap) {
            $before = $recap->getTitle();
            if ($dryRun) {
                $affected = $this->unicodeStyles->hasStyledCharacters($recap->getTitle())
                    || $this->unicodeStyles->hasStyledCharacters($recap->getMetaTitle())
                    || $this->unicodeStyles->hasStyledCharacters($recap->getMetaDescription())
                    || $this->unicodeStyles->hasStyledCharacters($recap->getContent());
            } else {
                $affected = $this->unicodeStyles->normalizeRecap($recap);
            }

            if ($affected) {
                $changedRecaps[] = $recap;
                $io->writeln(sprintf('%s récap n°%d : %s → %s', $dryRun ? 'À corriger' : 'Corrigé', $recap->getId(), $before, $dryRun ? $this->unicodeStyles->toPlain($before) : $recap->getTitle()));
            }
        }

        if ([] === $changed && [] === $changedRecaps) {
            $io->success('Aucun article ni récap ne contient de faux gras Unicode.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note(sprintf('%d article(s) et %d récap(s) à corriger. Relancez sans --dry-run pour appliquer.', \count($changed), \count($changedRecaps)));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        foreach ($changed as $article) {
            // Vide le cache des listes et garde une révision dans l'historique.
            $this->articleSaver->afterSave($article, wasPublished: ArticleStatus::Published === $article->getStatus());
        }

        $io->success(sprintf('%d article(s) et %d récap(s) corrigé(s).', \count($changed), \count($changedRecaps)));

        return Command::SUCCESS;
    }
}
