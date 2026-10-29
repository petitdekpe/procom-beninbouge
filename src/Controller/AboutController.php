<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\DossierRepository;
use App\Repository\MediaRepository;
use App\Repository\TeamMemberRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « À propos » page, modelled on the beninroots.bj about section (mission,
 * vision, values, team, key figures). The editorial copy lives in
 * templates/about/index.html.twig; everything factual comes from the
 * database: the key figures, and the team entered in the
 * back-office (App\Controller\Admin\TeamMemberController).
 */
final class AboutController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private CategoryRepository $categoryRepository,
        private DossierRepository $dossierRepository,
        private MediaRepository $mediaRepository,
        private TeamMemberRepository $teamMemberRepository,
    ) {
    }

    #[Route('/a-propos', name: 'app_about', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('about/index.html.twig', [
            'team' => $this->teamMemberRepository->findVisibleOrdered(),
            'figures' => [
                ['value' => $this->articleRepository->countPublished(), 'label' => 'articles publiés'],
                ['value' => $this->mediaRepository->countPublicVideos(), 'label' => 'vidéos'],
                ['value' => $this->dossierRepository->count([]), 'label' => 'dossiers'],
                ['value' => $this->categoryRepository->count([]), 'label' => 'rubriques'],
            ],
        ]);
    }
}
