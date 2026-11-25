<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\Enum\ActivityAction;
use App\Entity\Enum\AnnouncementStatus;
use App\Entity\User;
use App\Form\AnnouncementPricingType;
use App\Form\AnnouncementSubmissionType;
use App\Repository\AdEventRepository;
use App\Repository\AnnouncementRepository;
use App\Repository\SiteSettingsRepository;
use App\Service\ActivityLogger;
use App\Service\AnnouncementPricing;
use App\Service\DocumentUploader;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Review queue for paid announcements. Every paid announcement must be
 * approved or rejected within Announcement::REVIEW_DELAY_HOURS (48 h):
 * the « À valider » tab lists the oldest first and flags overdue ones.
 */
#[IsGranted('announcements.manage')]
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

    /** Durée maximale d'une annonce de la rédaction, hors grille tarifaire. */
    private const STAFF_MAX_DAYS = 365;

    public function __construct(
        private AnnouncementRepository $announcementRepository,
        private AdEventRepository $adEventRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
        private ImageUploader $imageUploader,
        private DocumentUploader $documentUploader,
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
    #[IsGranted('announcements.pricing')]
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

    /**
     * Annonce déposée par la rédaction : même formulaire que le dépôt
     * public, sans conditions à accepter ni paiement. Elle est gratuite et
     * mise en ligne dès l'enregistrement (Announcement::publishByStaff()).
     */
    #[Route('/nouvelle', name: 'admin_announcement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, AnnouncementPricing $pricing): Response
    {
        $user = $this->reviewer();
        $announcement = (new Announcement())
            ->setDurationDays(max($pricing->minDays(), 30))
            ->setShowContact(false);

        $form = $this->createForm(AnnouncementSubmissionType::class, $announcement, [
            'min_days' => 1,
            'max_days' => self::STAFF_MAX_DAYS,
            'staff' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeUploads($form, $announcement);

            if ($form->isValid()) {
                $announcement->publishByStaff($user, new \DateTimeImmutable());
                $this->entityManager->persist($announcement);
                $this->entityManager->flush();
                $this->activityLogger->log(ActivityAction::Publication, 'Announcement', $announcement->getId(), 'by=staff');

                $this->addFlash('success', sprintf('Annonce %s publiée : en ligne jusqu\'au %s.', $announcement->getReference(), $announcement->getEndsAt()?->format('d/m/Y à H:i')));

                return $this->redirectToRoute('admin_announcement_index', ['tab' => 'online']);
            }
        }

        return $this->render('admin/announcement/new.html.twig', [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Correction d'une annonce, payée ou de la rédaction, quel que soit son
     * statut : le contenu, les coordonnées et la formule changent, le statut
     * et le paiement jamais. Sur une annonce publiée, la fin de diffusion
     * suit la nouvelle durée (Announcement::setDurationDays()).
     */
    #[Route('/{id}/modifier', name: 'admin_announcement_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Announcement $announcement): Response
    {
        $form = $this->createForm(AnnouncementSubmissionType::class, $announcement, [
            'min_days' => 1,
            'max_days' => max(self::STAFF_MAX_DAYS, $announcement->getDurationDays()),
            'staff' => true,
        ]);
        if ($announcement->getImage()) {
            $form->add('removeImage', CheckboxType::class, [
                'label' => 'Retirer l\'image actuelle',
                'mapped' => false,
                'required' => false,
            ]);
        }
        if ($announcement->getDocument()) {
            $form->add('removeDocument', CheckboxType::class, [
                'label' => 'Retirer le document actuel',
                'mapped' => false,
                'required' => false,
            ]);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeUploads($form, $announcement);

            if ($form->isValid()) {
                $this->entityManager->flush();
                $this->activityLogger->log(ActivityAction::Moderation, 'Announcement', $announcement->getId(), 'edited');
                $this->addFlash('success', sprintf('Annonce %s modifiée.', $announcement->getReference()));

                return $this->redirectToRoute('admin_announcement_show', ['id' => $announcement->getId()]);
            }
        }

        return $this->render('admin/announcement/edit.html.twig', [
            'form' => $form,
            'announcement' => $announcement,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'admin_announcement_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Announcement $announcement): Response
    {
        return $this->render('admin/announcement/show.html.twig', [
            'announcement' => $announcement,
            'stats' => $this->adEventRepository->statsByAnnouncement()[$announcement->getId()] ?? null,
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

    /**
     * Enregistre l'image et le document téléversés, ou retire ceux dont la
     * case « Retirer » est cochée (modification) quand rien ne les remplace.
     * Une erreur d'enregistrement devient une erreur du champ, ce qui rend
     * le formulaire invalide.
     */
    private function storeUploads(FormInterface $form, Announcement $announcement): void
    {
        $uploads = [
            'imageFile' => ['removeImage', fn (UploadedFile $file) => $announcement->setImage($this->imageUploader->upload($file)), fn () => $announcement->setImage(null)],
            'documentFile' => ['removeDocument', fn (UploadedFile $file) => $announcement->setDocument($this->documentUploader->upload($file)), fn () => $announcement->setDocument(null)],
        ];

        foreach ($uploads as $field => [$removeField, $store, $remove]) {
            $file = $form->get($field)->getData();
            if ($file instanceof UploadedFile) {
                try {
                    $store($file);
                } catch (FileException $e) {
                    $form->get($field)->addError(new FormError($e->getMessage()));
                }
            } elseif ($form->has($removeField) && true === $form->get($removeField)->getData()) {
                $remove();
            }
        }
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
