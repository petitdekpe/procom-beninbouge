<?php

namespace App\Controller;

use App\Repository\RecapRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RecapController extends AbstractController
{
    private const OTHERS_SIZE = 5;

    public function __construct(
        private RecapRepository $recapRepository,
    ) {
    }

    /**
     * Same preview rule as App\Controller\ArticleController: a draft renders
     * identically, but only to a logged-in editor.
     */
    #[Route('/recap/{slug}', name: 'app_recap_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $recap = $this->recapRepository->findOneBy(['slug' => $slug]);

        if (!$recap || !$recap->hasVideo() || (!$recap->isPublished() && !$this->isGranted('ROLE_EDITOR'))) {
            throw $this->createNotFoundException('Récap introuvable.');
        }

        return $this->render('recap/show.html.twig', [
            'recap' => $recap,
            'others' => $this->recapRepository->findLatestPublished(self::OTHERS_SIZE, $recap),
        ]);
    }
}
