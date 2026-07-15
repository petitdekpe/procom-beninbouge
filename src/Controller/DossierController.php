<?php

namespace App\Controller;

use App\Entity\Enum\ArticleStatus;
use App\Repository\DossierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DossierController extends AbstractController
{
    public function __construct(
        private DossierRepository $dossierRepository,
    ) {
    }

    #[Route('/dossiers', name: 'app_dossier_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dossier/index.html.twig', [
            'dossiers' => $this->dossierRepository->findLatest(),
        ]);
    }

    #[Route('/dossier/{slug}', name: 'app_dossier_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $dossier = $this->dossierRepository->findOneBy(['slug' => $slug]);

        if (!$dossier) {
            throw $this->createNotFoundException('Dossier introuvable.');
        }

        // Only published articles are shown publicly, even though the
        // admin can add a draft to a dossier ahead of time.
        $articles = array_values(array_filter(
            $dossier->getArticles()->toArray(),
            static fn ($article) => ArticleStatus::Published === $article->getStatus(),
        ));
        usort($articles, static fn ($a, $b) => $b->getPublishedAt() <=> $a->getPublishedAt());

        return $this->render('dossier/show.html.twig', [
            'dossier' => $dossier,
            'articles' => $articles,
        ]);
    }
}
