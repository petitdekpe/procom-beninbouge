<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\MediaRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TagController extends AbstractController
{
    public function __construct(
        private TagRepository $tagRepository,
        private ArticleRepository $articleRepository,
        private MediaRepository $mediaRepository,
    ) {
    }

    #[Route('/mot-cle/{slug}', name: 'app_tag_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $tag = $this->tagRepository->findBySlug($slug);

        if (!$tag) {
            throw $this->createNotFoundException('Mot-clé introuvable.');
        }

        return $this->render('tag/show.html.twig', [
            'tag' => $tag,
            'articles' => $this->articleRepository->findLatestPublishedByTag($tag, 60),
            'media' => $this->mediaRepository->findLatestPublishedByTag($tag, 24),
        ]);
    }
}
