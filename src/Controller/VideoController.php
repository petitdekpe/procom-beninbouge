<?php

namespace App\Controller;

use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VideoController extends AbstractController
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {
    }

    private const PER_PAGE = 12;

    #[Route('/videos', name: 'app_videos', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $total = $this->mediaRepository->countPublicVideos();
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        // Valeur non numérique ou < 1 : page 1 plutôt qu'une erreur 400.
        $page = (int) filter_var($request->query->get('page', 1), \FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

        // Page hors limites (ancien lien, saisie manuelle) : retour à la dernière page.
        if ($page > $pageCount) {
            return $this->redirectToRoute('app_videos', $pageCount > 1 ? ['page' => $pageCount] : []);
        }

        return $this->render('video/index.html.twig', [
            'videos' => $this->mediaRepository->findPublicVideosPage($page, self::PER_PAGE),
            'page' => $page,
            'pageCount' => $pageCount,
            'total' => $total,
        ]);
    }
}
