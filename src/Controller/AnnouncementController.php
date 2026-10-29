<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\Enum\AnnouncementCategory;
use App\Entity\Enum\AnnouncementStatus;
use App\Form\AnnouncementSubmissionType;
use App\Repository\AnnouncementRepository;
use App\Service\AnnouncementPayment;
use App\Service\AnnouncementPricing;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Public side of « Annonces & Opportunités »: the listing, an announcement's
 * page, the paid submission form and the advertiser's tracking page. See
 * App\Entity\Announcement for the whole lifecycle.
 */
#[Route('/annonces')]
final class AnnouncementController extends AbstractController
{
    private const PER_PAGE = 12;

    public function __construct(
        private AnnouncementRepository $announcementRepository,
        private AnnouncementPricing $pricing,
        private AnnouncementPayment $payment,
        private ImageUploader $imageUploader,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'app_announcement_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $category = AnnouncementCategory::tryFrom((string) $request->query->get('categorie'));
        $total = $this->announcementRepository->countOnline($category);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));
        $page = (int) filter_var($request->query->get('page', 1), \FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $routeParams = $category ? ['categorie' => $category->value] : [];

        if ($page > $pageCount) {
            return $this->redirectToRoute('app_announcement_index', $pageCount > 1 ? $routeParams + ['page' => $pageCount] : $routeParams);
        }

        return $this->render('announcement/index.html.twig', [
            'announcements' => $this->announcementRepository->findOnlinePage($category, $page, self::PER_PAGE),
            'currentCategory' => $category,
            'categoryCounts' => $this->announcementRepository->countOnlineByCategory(),
            'totalOnline' => $this->announcementRepository->countOnline(),
            'page' => $page,
            'pageCount' => $pageCount,
            'routeParams' => $routeParams,
            'startingPrice' => $this->pricing->quote($this->pricing->minDays(), false, false),
            'minDays' => $this->pricing->minDays(),
        ]);
    }

    #[Route('/publier', name: 'app_announcement_submit', methods: ['GET', 'POST'])]
    public function submit(Request $request): Response
    {
        $announcement = new Announcement();
        $announcement->setDurationDays($this->pricing->minDays());

        $form = $this->createForm(AnnouncementSubmissionType::class, $announcement, [
            'min_days' => $this->pricing->minDays(),
            'max_days' => $this->pricing->maxDays(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('imageFile')->getData();
            if ($image instanceof UploadedFile) {
                $announcement->setImage($this->imageUploader->upload($image));
            }

            // Montant toujours recalculé côté serveur, jamais repris du formulaire.
            $announcement->setAmount($this->pricing->quote(
                $announcement->getDurationDays(),
                $announcement->isWithHomeCard(),
                $announcement->isWithPopup(),
            ));

            $this->entityManager->persist($announcement);
            $this->entityManager->flush();

            return $this->redirectToPayment($announcement);
        }

        return $this->render('announcement/submit.html.twig', [
            'form' => $form,
            'rates' => $this->pricing->rates(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * FedaPay sends the advertiser back here after checkout. The outcome is
     * read from FedaPay (never from the query string), then the advertiser
     * lands on their tracking page.
     */
    #[Route('/paiement/{token}', name: 'app_announcement_payment_return', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function paymentReturn(string $token): Response
    {
        $announcement = $this->findByToken($token);

        try {
            $this->payment->refresh($announcement);
        } catch (\Throwable $e) {
            // Le webhook prendra le relais ; le suivi affichera « en attente de paiement » d'ici là.
            $this->logger->warning('Vérification FedaPay impossible au retour de paiement.', ['reference' => $announcement->getReference(), 'exception' => $e]);
        }

        return $this->redirectToRoute('app_announcement_track', ['token' => $token]);
    }

    #[Route('/suivi/{token}', name: 'app_announcement_track', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function track(string $token): Response
    {
        return $this->render('announcement/track.html.twig', [
            'announcement' => $this->findByToken($token),
        ]);
    }

    /** Relance le paiement d'une annonce restée impayée (paiement abandonné ou refusé). */
    #[Route('/suivi/{token}/payer', name: 'app_announcement_retry_payment', requirements: ['token' => '[a-f0-9]{48}'], methods: ['POST'])]
    public function retryPayment(Request $request, string $token): Response
    {
        $announcement = $this->findByToken($token);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))
            || !\in_array($announcement->getStatus(), [AnnouncementStatus::PendingPayment, AnnouncementStatus::PaymentFailed], true)) {
            return $this->redirectToRoute('app_announcement_track', ['token' => $token]);
        }

        return $this->redirectToPayment($announcement);
    }

    #[Route('/{reference}', name: 'app_announcement_show', requirements: ['reference' => 'ANN-[A-Z0-9]{6}'], methods: ['GET'])]
    public function show(string $reference): Response
    {
        $announcement = $this->announcementRepository->findOnlineOneByReference($reference)
            ?? throw $this->createNotFoundException("Cette annonce n'existe pas ou n'est plus en ligne.");

        return $this->render('announcement/show.html.twig', [
            'announcement' => $announcement,
        ]);
    }

    private function redirectToPayment(Announcement $announcement): Response
    {
        try {
            $paymentUrl = $this->payment->initiate(
                $announcement,
                $this->generateUrl('app_announcement_payment_return', ['token' => $announcement->getTrackingToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Ouverture du paiement FedaPay impossible pour une annonce.', ['reference' => $announcement->getReference(), 'exception' => $e]);
            $this->addFlash('announcement_error', "Le service de paiement est momentanément indisponible. Votre annonce est enregistrée : vous pouvez relancer le paiement depuis cette page.");

            return $this->redirectToRoute('app_announcement_track', ['token' => $announcement->getTrackingToken()]);
        }

        return $this->redirect($paymentUrl);
    }

    private function findByToken(string $token): Announcement
    {
        return $this->announcementRepository->findOneBy(['trackingToken' => $token])
            ?? throw $this->createNotFoundException('Annonce introuvable.');
    }
}
