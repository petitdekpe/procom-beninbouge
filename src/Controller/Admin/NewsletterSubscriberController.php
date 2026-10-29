<?php

namespace App\Controller\Admin;

use App\Entity\Enum\ActivityAction;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Newsletter sign-ups from the footer form. The site doesn't send
 * newsletters itself: the CSV export is meant for the mailing tool.
 */
#[IsGranted('ROLE_EDITOR_IN_CHIEF')]
#[Route('/admin/newsletter')]
final class NewsletterSubscriberController extends AbstractController
{
    public function __construct(
        private NewsletterSubscriberRepository $newsletterSubscriberRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'admin_newsletter_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/newsletter/index.html.twig', [
            'subscribers' => $this->newsletterSubscriberRepository->findAllNewestFirst(),
        ]);
    }

    #[Route('/export.csv', name: 'admin_newsletter_export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        $subscribers = $this->newsletterSubscriberRepository->findAllNewestFirst();

        $response = new StreamedResponse(static function () use ($subscribers): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents lisibles dans Excel
            fputcsv($out, ['email', 'inscrit_le'], ';');
            foreach ($subscribers as $subscriber) {
                // Adresse saisie par un visiteur : neutralise « =… », « +… », etc.
                // qu'Excel interpréterait comme une formule.
                $email = (string) $subscriber->getEmail();
                if ('' !== $email && str_contains('=+-@', $email[0])) {
                    $email = "'".$email;
                }
                fputcsv($out, [$email, $subscriber->getCreatedAt()?->format('Y-m-d H:i')], ';');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="newsletter-'.date('Y-m-d').'.csv"');

        return $response;
    }

    #[Route('/{id}/delete', name: 'admin_newsletter_delete', methods: ['POST'])]
    public function delete(Request $request, NewsletterSubscriber $subscriber): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $id = $subscriber->getId();
            $this->entityManager->remove($subscriber);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Deletion, 'NewsletterSubscriber', $id);

            $this->addFlash('success', 'Abonné supprimé.');
        }

        return $this->redirectToRoute('admin_newsletter_index');
    }
}
