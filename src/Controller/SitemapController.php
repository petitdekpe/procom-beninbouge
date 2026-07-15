<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\DossierRepository;
use App\Repository\PageRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitemapController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private CategoryRepository $categoryRepository,
        private DossierRepository $dossierRepository,
        private PageRepository $pageRepository,
        private TagRepository $tagRepository,
    ) {
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'], format: 'xml')]
    public function index(): Response
    {
        $response = $this->render('sitemap.xml.twig', [
            'articles' => $this->articleRepository->findLatestPublished(5000),
            'categories' => $this->categoryRepository->findAll(),
            'dossiers' => $this->dossierRepository->findBy([], ['id' => 'DESC']),
            'pages' => $this->pageRepository->findPublished(),
            'tags' => $this->tagRepository->findBy([], ['name' => 'ASC']),
        ]);

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
