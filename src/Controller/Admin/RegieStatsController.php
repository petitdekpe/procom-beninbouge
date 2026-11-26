<?php

namespace App\Controller\Admin;

use App\Service\RegieReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Performance » de la régie : affichages, clics et taux de clic des
 * publicités du slider et des annonces, sur la période choisie. Les
 * événements viennent de App\Controller\AdTrackingController ; ils ne
 * couvrent que la période écoulée depuis la mise en place de la mesure.
 */
#[IsGranted('stats.view')]
#[Route('/admin/regie/statistiques')]
final class RegieStatsController extends AbstractController
{
    #[Route('', name: 'admin_regie_stats', methods: ['GET'])]
    public function index(Request $request, RegieReport $report): Response
    {
        return $this->render('admin/regie/stats.html.twig', [
            'periods' => RegieReport::PERIODS,
        ] + $report->build($request->query->getInt('jours', 30)));
    }
}
