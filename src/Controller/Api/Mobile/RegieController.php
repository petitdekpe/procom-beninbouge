<?php

namespace App\Controller\Api\Mobile;

use App\Entity\Announcement;
use App\Entity\Enum\ActivityAction;
use App\Entity\Enum\AnnouncementCategory;
use App\Entity\Enum\AnnouncementStatus;
use App\Entity\HomeBanner;
use App\Form\AnnouncementSubmissionType;
use App\Form\HomeBannerAdminType;
use App\Repository\AdEventRepository;
use App\Repository\AnnouncementRepository;
use App\Repository\HomeBannerRepository;
use App\Service\ActivityLogger;
use App\Service\AnnouncementPricing;
use App\Service\ImageUploader;
use App\Service\Mobile\HtmlContentPolicy;
use App\Service\Mobile\RequestKeys;
use App\Service\RegieReport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * La régie dans l'application mobile : publicités du slider, annonces
 * payantes et performances. Les saisies passent par les mêmes formulaires
 * que le back-office, donc les mêmes règles de validation ; les droits sont
 * les mêmes permissions.
 */
#[Route('/api/mobile/v1')]
final class RegieController extends AbstractMobileController
{
    private const BANNER_TABS = ['live', 'scheduled', 'expired', 'paused'];
    private const ANNOUNCEMENT_TABS = ['review', 'online', 'expired', 'rejected', 'unpaid'];
    private const BANNER_FIELDS = ['title', 'advertiser', 'linkUrl', 'durationDays', 'amount', 'contactName', 'contactEmail', 'contactPhone', 'enabled'];
    private const ANNOUNCEMENT_FIELDS = ['category', 'title', 'organization', 'location', 'deadline', 'description', 'externalUrl', 'contactName', 'contactEmail', 'contactPhone', 'showContact', 'durationDays', 'withHomeCard', 'withPopup'];
    private const STAFF_MAX_DAYS = 365;

    public function __construct(
        private HomeBannerRepository $bannerRepository,
        private AnnouncementRepository $announcementRepository,
        private AdEventRepository $adEventRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploader $imageUploader,
        private ActivityLogger $activityLogger,
        private RequestKeys $requestKeys,
    ) {
    }

    // ---- Publicités du slider ------------------------------------------

    #[IsGranted('ads.manage')]
    #[Route('/banners', name: 'mobile_banner_index', methods: ['GET'])]
    public function banners(Request $request): JsonResponse
    {
        $tab = (string) $request->query->get('tab', 'live');
        $tab = \in_array($tab, self::BANNER_TABS, true) ? $tab : 'live';
        $stats = $this->adEventRepository->statsByBanner();

        return $this->json([
            'items' => array_map(fn (HomeBanner $banner) => $this->banner($banner, $stats), $this->bannerRepository->findForAdmin($tab)),
            'counts' => $this->bannerRepository->countByTab(),
        ]);
    }

    #[IsGranted('ads.manage')]
    #[Route('/banners', name: 'mobile_banner_create', methods: ['POST'])]
    public function createBanner(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $data = $this->payload($request);
        $clientId = (string) ($data['clientId'] ?? '');

        $existing = $this->requestKeys->existing($user, $clientId, 'banner', HomeBanner::class);
        if (null !== $existing) {
            return $this->json(['banner' => $this->banner($existing)]);
        }

        $banner = new HomeBanner();
        $failure = $this->submitBanner($banner, $data, $request);
        if (null !== $failure) {
            return $failure;
        }

        $banner->setPosition($this->bannerRepository->nextPosition());
        $this->entityManager->wrapInTransaction(function () use ($banner, $user, $clientId): void {
            $this->entityManager->persist($banner);
            $this->entityManager->flush();
            $this->requestKeys->remember($user, $clientId, 'banner', (int) $banner->getId());
        });
        $this->activityLogger->log(ActivityAction::Publication, 'HomeBanner', $banner->getId(), 'ad');

        return $this->json(['banner' => $this->banner($banner)], 201);
    }

    /** POST et non PUT : PHP ne lit les envois multipart (image) qu'en POST. */
    #[IsGranted('ads.manage')]
    #[Route('/banners/{id}', name: 'mobile_banner_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateBanner(Request $request, HomeBanner $banner): JsonResponse
    {
        $failure = $this->submitBanner($banner, $this->payload($request), $request);
        if (null !== $failure) {
            return $failure;
        }

        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Moderation, 'HomeBanner', $banner->getId(), 'edited');

        return $this->json(['banner' => $this->banner($banner)]);
    }

    /** L'état voulu est envoyé explicitement : renvoyer la demande ne l'inverse pas. */
    #[IsGranted('ads.manage')]
    #[Route('/banners/{id}/enabled', name: 'mobile_banner_enabled', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setBannerEnabled(Request $request, HomeBanner $banner): JsonResponse
    {
        $enabled = $this->payload($request)['enabled'] ?? null;
        if (!\is_bool($enabled)) {
            return $this->error('Indiquez l\'état voulu (enabled).', 400);
        }

        if ($banner->isEnabled() !== $enabled) {
            $banner->setEnabled($enabled);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'HomeBanner', $banner->getId(), $enabled ? 'resumed' : 'paused');
        }

        return $this->json(['banner' => $this->banner($banner)]);
    }

    // ---- Annonces payantes ---------------------------------------------

    #[IsGranted('announcements.manage')]
    #[Route('/announcements', name: 'mobile_announcement_index', methods: ['GET'])]
    public function announcements(Request $request): JsonResponse
    {
        $tab = (string) $request->query->get('tab', 'review');
        $tab = \in_array($tab, self::ANNOUNCEMENT_TABS, true) ? $tab : 'review';

        return $this->json([
            'items' => array_map($this->announcement(...), $this->announcementRepository->findForAdmin($tab)),
            'pendingCount' => $this->announcementRepository->countPendingReview(),
            'categories' => array_map(
                static fn (AnnouncementCategory $c) => ['value' => $c->value, 'label' => $c->label()],
                AnnouncementCategory::cases(),
            ),
        ]);
    }

    #[IsGranted('announcements.manage')]
    #[Route('/announcements/{id}', name: 'mobile_announcement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showAnnouncement(Announcement $announcement): JsonResponse
    {
        return $this->json(['announcement' => $this->announcement($announcement) + [
            'stats' => $this->adEventRepository->statsByAnnouncement()[$announcement->getId()] ?? null,
        ]]);
    }

    /** Annonce de la rédaction : gratuite et en ligne dès l'enregistrement. */
    #[IsGranted('announcements.manage')]
    #[Route('/announcements', name: 'mobile_announcement_create', methods: ['POST'])]
    public function createAnnouncement(Request $request, AnnouncementPricing $pricing, HtmlContentPolicy $contentPolicy): JsonResponse
    {
        $user = $this->currentUser();
        $data = $this->payload($request);
        $clientId = (string) ($data['clientId'] ?? '');

        $existing = $this->requestKeys->existing($user, $clientId, 'announcement', Announcement::class);
        if (null !== $existing) {
            return $this->json(['announcement' => $this->announcement($existing)]);
        }

        if (!$contentPolicy->isSupported((string) ($data['description'] ?? ''))) {
            return $this->error('La description contient une mise en forme non autorisée.', 422);
        }
        if (!is_numeric($data['durationDays'] ?? null)) {
            return $this->error('Indiquez une durée.', 422, ['durationDays' => 'Indiquez une durée.']);
        }

        $announcement = (new Announcement())
            ->setDurationDays(max($pricing->minDays(), 30))
            ->setShowContact(false);
        $form = $this->createForm(AnnouncementSubmissionType::class, $announcement, [
            'min_days' => 1,
            'max_days' => self::STAFF_MAX_DAYS,
            'staff' => true,
            'csrf_protection' => false,
        ]);

        $fields = array_intersect_key($data, array_flip(self::ANNOUNCEMENT_FIELDS));
        $image = $request->files->get('imageFile');
        if ($image instanceof UploadedFile) {
            $fields['imageFile'] = $image;
        }
        $form->submit($fields, false);

        if (!$form->isValid() || !$this->storeImage($form, $announcement->setImage(...))) {
            return $this->formErrors($form);
        }
        // Les champs obligatoires non envoyés échappent à la validation partielle du formulaire.
        if (null === $announcement->getCategory() || !$announcement->getTitle() || !$announcement->getOrganization() || !$announcement->getDescription()) {
            return $this->error('Catégorie, titre, organisation et description sont obligatoires.', 422);
        }

        $announcement->publishByStaff($user, new \DateTimeImmutable());
        $this->entityManager->wrapInTransaction(function () use ($announcement, $user, $clientId): void {
            $this->entityManager->persist($announcement);
            $this->entityManager->flush();
            $this->requestKeys->remember($user, $clientId, 'announcement', (int) $announcement->getId());
        });
        $this->activityLogger->log(ActivityAction::Publication, 'Announcement', $announcement->getId(), 'by=staff');

        return $this->json(['announcement' => $this->announcement($announcement)], 201);
    }

    #[IsGranted('announcements.manage')]
    #[Route('/announcements/{id}/approve', name: 'mobile_announcement_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Announcement $announcement): JsonResponse
    {
        if (AnnouncementStatus::PendingReview !== $announcement->getStatus()) {
            return $this->error('Cette annonce n\'attend plus de validation. Actualisez la liste.', 409);
        }

        $announcement->approve($this->currentUser(), new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Moderation, 'Announcement', $announcement->getId(), 'status=approved');

        return $this->json(['announcement' => $this->announcement($announcement)]);
    }

    #[IsGranted('announcements.manage')]
    #[Route('/announcements/{id}/reject', name: 'mobile_announcement_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(Request $request, Announcement $announcement): JsonResponse
    {
        if (!\in_array($announcement->getStatus(), [AnnouncementStatus::PendingReview, AnnouncementStatus::Approved], true)) {
            return $this->error('Cette annonce ne peut plus être refusée. Actualisez la liste.', 409);
        }

        $reason = trim((string) ($this->payload($request)['reason'] ?? ''));
        if ('' === $reason) {
            return $this->error('Indiquez le motif du refus : il est affiché à l\'annonceur sur sa page de suivi.', 422);
        }

        $announcement->reject($this->currentUser(), new \DateTimeImmutable(), mb_substr($reason, 0, 1000));
        $this->entityManager->flush();
        $this->activityLogger->log(ActivityAction::Moderation, 'Announcement', $announcement->getId(), 'status=rejected');

        return $this->json(['announcement' => $this->announcement($announcement)]);
    }

    // ---- Performances ---------------------------------------------------

    #[IsGranted('stats.view')]
    #[Route('/regie/stats', name: 'mobile_regie_stats', methods: ['GET'])]
    public function stats(Request $request, RegieReport $report): JsonResponse
    {
        $data = $report->build($request->query->getInt('days', 30));

        $data['banners'] = array_map(static function (array $row): array {
            $banner = $row['banner'];
            unset($row['banner']);

            return ['id' => $banner->getId(), 'title' => $banner->getTitle(), 'advertiser' => $banner->getAdvertiser(), 'reference' => $banner->getReference()] + $row;
        }, $data['banners']);

        $data['announcements'] = array_map(static function (array $row): array {
            $announcement = $row['announcement'];
            unset($row['announcement']);

            return ['id' => $announcement->getId(), 'title' => $announcement->getTitle(), 'advertiser' => $announcement->getOrganization(), 'reference' => $announcement->getReference()] + $row;
        }, $data['announcements']);

        return $this->json($data);
    }

    // ---- Outils -----------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function submitBanner(HomeBanner $banner, array $data, Request $request): ?JsonResponse
    {
        $isNew = null === $banner->getId();
        // Ces champs ne peuvent pas être vides : le formulaire ne sait pas leur affecter « rien ».
        foreach (['title' => 'Le titre est requis.', 'durationDays' => 'Indiquez une durée.', 'amount' => 'Indiquez le montant (0 s\'il est offert).'] as $field => $message) {
            if (($isNew && 'amount' !== $field && !isset($data[$field])) || (\array_key_exists($field, $data) && '' === trim((string) $data[$field]))) {
                return $this->error($message, 422, [$field => $message]);
            }
        }

        $form = $this->createForm(HomeBannerAdminType::class, $banner, ['csrf_protection' => false]);
        $fields = array_intersect_key($data, array_flip(self::BANNER_FIELDS));
        if (isset($data['startsAt'])) {
            $fields['startsAt'] = $this->parseDate($data['startsAt'])?->format('Y-m-d\TH:i');
        }
        $image = $request->files->get('imageFile');
        if ($image instanceof UploadedFile) {
            $fields['imageFile'] = $image;
        }
        $form->submit($fields, false);

        $valid = $form->isValid() && $this->storeImage($form, $banner->setImageUrl(...));
        if ($valid && !$banner->getImageUrl()) {
            $form->get('imageFile')->addError(new FormError('Une publicité a besoin d\'une image.'));
            $valid = false;
        }
        if ($valid && !$banner->getAdvertiser()) {
            $form->get('advertiser')->addError(new FormError('Le nom de l\'annonceur est requis.'));
            $valid = false;
        }

        return $valid ? null : $this->formErrors($form);
    }

    private function storeImage(FormInterface $form, \Closure $assign): bool
    {
        $file = $form->get('imageFile')->getData();
        if ($file instanceof UploadedFile) {
            try {
                $assign($this->imageUploader->upload($file));
            } catch (FileException $e) {
                $form->get('imageFile')->addError(new FormError($e->getMessage()));

                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, int>>|null $stats
     *
     * @return array<string, mixed>
     */
    private function banner(HomeBanner $banner, ?array $stats = null): array
    {
        $stats ??= $this->adEventRepository->statsByBanner();

        return [
            'id' => $banner->getId(),
            'reference' => $banner->getReference(),
            'title' => $banner->getTitle(),
            'advertiser' => $banner->getAdvertiser(),
            'contactName' => $banner->getContactName(),
            'contactEmail' => $banner->getContactEmail(),
            'contactPhone' => $banner->getContactPhone(),
            'imageUrl' => $banner->getImageUrl(),
            'linkUrl' => $banner->getLinkUrl(),
            'amount' => $banner->getAmount(),
            'durationDays' => $banner->getDurationDays(),
            'startsAt' => $this->date($banner->getStartsAt()),
            'endsAt' => $this->date($banner->getEndsAt()),
            'enabled' => $banner->isEnabled(),
            'status' => $banner->getStatus(),
            'statusLabel' => $banner->getStatusLabel(),
            'stats' => $stats[$banner->getId()] ?? ['impressions' => 0, 'clicks' => 0, 'readers' => 0],
        ];
    }

    /** @return array<string, mixed> */
    private function announcement(Announcement $a): array
    {
        return [
            'id' => $a->getId(),
            'reference' => $a->getReference(),
            'title' => $a->getTitle(),
            'organization' => $a->getOrganization(),
            'categoryLabel' => $a->getCategory()?->label(),
            'status' => $a->getStatus()->value,
            'statusLabel' => $a->isExpired() ? 'Expirée' : $a->getStatus()->label(),
            'online' => $a->isOnline(),
            'publicPath' => $a->isOnline() ? '/annonces/'.$a->getPublicKey() : null,
            'description' => $a->getDescription(),
            'location' => $a->getLocation(),
            'deadline' => $a->getDeadline()?->format('Y-m-d'),
            'externalUrl' => $a->getExternalUrl(),
            'image' => $a->getImage(),
            'document' => $a->getDocument(),
            'contactName' => $a->getContactName(),
            'contactEmail' => $a->getContactEmail(),
            'contactPhone' => $a->getContactPhone(),
            'durationDays' => $a->getDurationDays(),
            'withHomeCard' => $a->isWithHomeCard(),
            'withPopup' => $a->isWithPopup(),
            'amount' => $a->getAmount(),
            'currency' => $a->getCurrency(),
            'byStaff' => $a->isPublishedByStaff(),
            'paidAt' => $this->date($a->getPaidAt()),
            'reviewDeadline' => $this->date($a->getReviewDeadline()),
            'reviewOverdue' => $a->isReviewOverdue(),
            'rejectionReason' => $a->getRejectionReason(),
            'startsAt' => $this->date($a->getStartsAt()),
            'endsAt' => $this->date($a->getEndsAt()),
        ];
    }
}
