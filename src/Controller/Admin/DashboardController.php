<?php

namespace App\Controller\Admin;

use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private CategoryRepository $categoryRepository,
        private AuthorRepository $authorRepository,
        private ProjectRepository $projectRepository,
        private MediaRepository $mediaRepository,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'counts' => [
                'articles' => $this->articleRepository->count([]),
                'categories' => $this->categoryRepository->count([]),
                'authors' => $this->authorRepository->count([]),
                'projects' => $this->projectRepository->count([]),
                'media' => $this->mediaRepository->count([]),
            ],
        ]);
    }
}
