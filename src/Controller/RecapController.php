<?php

namespace App\Controller;

use App\Repository\RecapRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RecapController extends AbstractController
{
    private const OTHERS_SIZE = 5;
    private const PER_PAGE = 15;

    public function __construct(
        private RecapRepository $recapRepository,
    ) {
    }

    #[Route('/recaps', name: 'app_recap_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $total = $this->recapRepository->countPublished();
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        // Valeur non numérique ou < 1 : page 1 plutôt qu'une erreur 400.
        $page = (int) filter_var($request->query->get('page', 1), \FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

        // Page hors limites (ancien lien, saisie manuelle) : retour à la dernière page.
        if ($page > $pageCount) {
            return $this->redirectToRoute('app_recap_index', $pageCount > 1 ? ['page' => $pageCount] : []);
        }

        return $this->render('recap/index.html.twig', [
            'recaps' => $this->recapRepository->findPublishedPage($page, self::PER_PAGE),
            'page' => $page,
            'pageCount' => $pageCount,
            'total' => $total,
        ]);
    }

    /**
     * Same preview rule as App\Controller\ArticleController: a draft renders
     * identically, but only to a logged-in editor.
     */
    #[Route('/recap/{slug}', name: 'app_recap_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $recap = $this->recapRepository->findOneBy(['slug' => $slug]);

        if (!$recap || !$recap->hasVideo() || (!$recap->isPublished() && !$this->isGranted('admin.access'))) {
            throw $this->createNotFoundException('Récap introuvable.');
        }

        return $this->render('recap/show.html.twig', [
            'recap' => $recap,
            'others' => $this->recapRepository->findLatestPublished(self::OTHERS_SIZE, $recap),
        ]);
    }
}
