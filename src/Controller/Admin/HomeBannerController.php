<?php

namespace App\Controller\Admin;

use App\Entity\Enum\ActivityAction;
use App\Entity\HomeBanner;
use App\Form\HomeBannerAdminType;
use App\Repository\HomeBannerRepository;
use App\Service\ActivityLogger;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Publicités du slider de l'accueil (images rectangulaires affichées au-dessus
 * de l'actualité), gérées comme les annonces : onglets par statut (en ligne,
 * programmées, terminées, en pause), annonceur et contact, période de
 * diffusion, montant. L'ordre du slider se règle dans l'onglet « En ligne » ;
 * l'emplacement du slider sur la page, dans « Page d'accueil ».
 */
#[IsGranted('ads.manage')]
#[Route('/admin/bannieres')]
final class HomeBannerController extends AbstractController
{
    private const TABS = [
        'live' => 'En ligne',
        'scheduled' => 'Programmées',
        'expired' => 'Terminées',
        'paused' => 'En pause',
    ];

    public function __construct(
        private HomeBannerRepository $bannerRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploader $imageUploader,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'admin_home_banner_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $tab = \array_key_exists((string) $request->query->get('tab'), self::TABS) ? (string) $request->query->get('tab') : 'live';

        return $this->render('admin/home_banner/index.html.twig', [
            'banners' => $this->bannerRepository->findForAdmin($tab),
            'tabs' => self::TABS,
            'currentTab' => $tab,
            'counts' => $this->bannerRepository->countByTab(),
        ]);
    }

    #[Route('/new', name: 'admin_home_banner_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $banner = new HomeBanner();
        $form = $this->createForm(HomeBannerAdminType::class, $banner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->handleImage($banner, $form)) {
            $banner->setPosition($this->bannerRepository->nextPosition());

            $this->entityManager->persist($banner);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Publication, 'HomeBanner', $banner->getId(), 'ad');

            $this->addFlash('success', sprintf('Publicité %s enregistrée (%s, jusqu\'au %s).', $banner->getReference(), mb_strtolower($banner->getStatusLabel()), $banner->getEndsAt()?->format('d/m/Y à H:i')));

            return $this->redirectToRoute('admin_home_banner_index', ['tab' => $banner->getStatus()]);
        }

        return $this->render('admin/home_banner/new.html.twig', ['banner' => $banner, 'form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}/edit', name: 'admin_home_banner_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, HomeBanner $banner): Response
    {
        $form = $this->createForm(HomeBannerAdminType::class, $banner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->handleImage($banner, $form)) {
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'HomeBanner', $banner->getId(), 'edited');

            $this->addFlash('success', sprintf('Publicité %s mise à jour.', $banner->getReference()));

            return $this->redirectToRoute('admin_home_banner_index', ['tab' => $banner->getStatus()]);
        }

        return $this->render('admin/home_banner/edit.html.twig', ['banner' => $banner, 'form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}/toggle', name: 'admin_home_banner_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, HomeBanner $banner): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $banner->setEnabled(!$banner->isEnabled());
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Moderation, 'HomeBanner', $banner->getId(), $banner->isEnabled() ? 'resumed' : 'paused');

            $this->addFlash('success', $banner->isEnabled() ? sprintf('Publicité %s reprise.', $banner->getReference()) : sprintf('Publicité %s mise en pause.', $banner->getReference()));
        }

        return $this->redirectToRoute('admin_home_banner_index', ['tab' => $banner->getStatus()]);
    }

    #[Route('/{id}/move-up', name: 'admin_home_banner_move_up', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function moveUp(Request $request, HomeBanner $banner): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->swapWithNeighbor($banner, -1);
        }

        return $this->redirectToRoute('admin_home_banner_index');
    }

    #[Route('/{id}/move-down', name: 'admin_home_banner_move_down', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function moveDown(Request $request, HomeBanner $banner): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->swapWithNeighbor($banner, 1);
        }

        return $this->redirectToRoute('admin_home_banner_index');
    }

    #[Route('/{id}/delete', name: 'admin_home_banner_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, HomeBanner $banner): Response
    {
        $tab = $banner->getStatus();

        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $reference = $banner->getReference();
            $this->entityManager->remove($banner);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Publicité %s supprimée.', $reference));
        }

        return $this->redirectToRoute('admin_home_banner_index', ['tab' => $tab]);
    }

    /**
     * Stores an uploaded image (it takes priority over a typed URL) and makes
     * sure the publicité ends up with one. Returns false — with an error on
     * the form, which is then shown again — when it doesn't.
     */
    private function handleImage(HomeBanner $banner, FormInterface $form): bool
    {
        $file = $form->get('imageFile')->getData();
        if ($file instanceof UploadedFile) {
            try {
                $banner->setImageUrl($this->imageUploader->upload($file));
            } catch (FileException $e) {
                $form->get('imageFile')->addError(new FormError($e->getMessage()));

                return false;
            }
        }

        if (!$banner->getImageUrl()) {
            $form->get('imageFile')->addError(new FormError('Une publicité a besoin d\'une image : téléversez un fichier ou renseignez une URL.'));

            return false;
        }

        return true;
    }

    /**
     * Same simple reordering as HomeSectionController, among the ads of the
     * same slider (the « En ligne » tab): swap positions with the neighbour.
     */
    private function swapWithNeighbor(HomeBanner $banner, int $direction): void
    {
        $live = $this->bannerRepository->findForAdmin('live');
        $index = array_search($banner, $live, true);
        $neighbor = false === $index ? null : ($live[$index + $direction] ?? null);

        if (null === $neighbor) {
            return;
        }

        // Positions égales possibles (données anciennes) : on renumérote d'abord.
        foreach ($this->bannerRepository->findAllOrdered() as $i => $item) {
            $item->setPosition($i + 1);
        }

        $bannerPosition = $banner->getPosition();
        $banner->setPosition($neighbor->getPosition());
        $neighbor->setPosition($bannerPosition);

        $this->entityManager->flush();
    }

    private function formResponse(FormInterface $form): Response
    {
        return new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
