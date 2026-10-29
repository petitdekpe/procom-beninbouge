<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\Enum\ActivityAction;
use App\Entity\Enum\AnnouncementStatus;
use App\Entity\User;
use App\Form\AnnouncementPricingType;
use App\Repository\AnnouncementRepository;
use App\Repository\SiteSettingsRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Review queue for paid announcements. Every paid announcement must be
 * approved or rejected within Announcement::REVIEW_DELAY_HOURS (48 h):
 * the « À valider » tab lists the oldest first and flags overdue ones.
 */
#[IsGranted('ROLE_EDITOR_IN_CHIEF')]
#[Route('/admin/annonces')]
final class AnnouncementController extends AbstractController
{
    private const TABS = [
        'review' => 'À valider',
        'online' => 'En ligne',
        'expired' => 'Expirées',
        'rejected' => 'Refusées',
        'unpaid' => 'Non payées',
    ];

    public function __construct(
        private AnnouncementRepository $announcementRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'admin_announcement_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tab = \array_key_exists((string) $request->query->get('tab'), self::TABS) ? (string) $request->query->get('tab') : 'review';

        return $this->render('admin/announcement/index.html.twig', [
            'announcements' => $this->announcementRepository->findForAdmin($tab),
            'tabs' => self::TABS,
            'currentTab' => $tab,
            'pendingCount' => $this->announcementRepository->countPendingReview(),
        ]);
    }

    /**
     * Prices are a business decision: ROLE_ADMIN only, like the rest of the
     * site settings they're stored in.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/tarifs', name: 'admin_announcement_pricing', methods: ['GET', 'POST'])]
    public function pricing(Request $request, SiteSettingsRepository $siteSettingsRepository): Response
    {
        $settings = $siteSettingsRepository->getSettings();
        $form = $this->createForm(AnnouncementPricingType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Tarifs des annonces enregistrés. Ils s\'appliquent aux prochaines annonces déposées.');

            return $this->redirectToRoute('admin_announcement_pricing');
        }

        return $this->render('admin/announcement/pricing.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_announcement_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Announcement $announcement): Response
    {
        return $this->render('admin/announcement/show.html.twig', [
            'announcement' => $announcement,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_announcement_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, Announcement $announcement): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))
            && AnnouncementStatus::PendingReview === $announcement->getStatus()) {
            $announcement->approve($this->reviewer(), new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'Announcement', $announcement->getId(), 'status=approved');

            $this->addFlash('success', sprintf('Annonce %s validée : en ligne jusqu\'au %s.', $announcement->getReference(), $announcement->getEndsAt()?->format('d/m/Y à H:i')));
        }

        return $this->redirectToRoute('admin_announcement_index');
    }

    #[Route('/{id}/reject', name: 'admin_announcement_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(Request $request, Announcement $announcement): Response
    {
        $reason = trim((string) $request->request->get('reason'));

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))
            || !\in_array($announcement->getStatus(), [AnnouncementStatus::PendingReview, AnnouncementStatus::Approved], true)) {
            return $this->redirectToRoute('admin_announcement_show', ['id' => $announcement->getId()]);
        }

        if ('' === $reason) {
            $this->addFlash('error', 'Indiquez le motif du refus : il est affiché à l\'annonceur sur sa page de suivi.');

            return $this->redirectToRoute('admin_announcement_show', ['id' => $announcement->getId()]);
        }

        $announcement->reject($this->reviewer(), new \DateTimeImmutable(), mb_substr($reason, 0, 1000));
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Moderation, 'Announcement', $announcement->getId(), 'status=rejected');

        $this->addFlash('success', sprintf('Annonce %s refusée.', $announcement->getReference()));

        return $this->redirectToRoute('admin_announcement_index');
    }

    private function reviewer(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
