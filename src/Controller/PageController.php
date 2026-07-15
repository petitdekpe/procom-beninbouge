<?php

namespace App\Controller;

use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    public function __construct(
        private PageRepository $pageRepository,
    ) {
    }

    #[Route('/page/{slug}', name: 'app_page_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $page = $this->pageRepository->findOneBy(['slug' => $slug]);

        if (!$page || !$page->isPublished()) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        return $this->render('page/show.html.twig', [
            'page' => $page,
        ]);
    }
}
