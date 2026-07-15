<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ArticleRepository $articleRepository,
        private MediaRepository $mediaRepository,
    ) {
    }

    #[Route('/rubrique/{slug}', name: 'app_category_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            throw $this->createNotFoundException('Rubrique introuvable.');
        }

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'articles' => $this->articleRepository->findLatestPublishedByCategory($category, 60),
            'media' => $this->mediaRepository->findLatestPublishedByCategory($category, 24),
        ]);
    }
}
