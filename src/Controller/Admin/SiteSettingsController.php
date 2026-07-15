<?php

namespace App\Controller\Admin;

use App\Form\SiteSettingsAdminType;
use App\Repository\SiteSettingsRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Site-wide configuration is more sensitive than editorial content (it
 * includes maintenance mode, which can take the whole public site down),
 * so this stays ROLE_ADMIN-only rather than the ROLE_EDITOR used by
 * Category/Media/HomeSection.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/settings')]
final class SiteSettingsController extends AbstractController
{
    public function __construct(
        private SiteSettingsRepository $siteSettingsRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploader $imageUploader,
    ) {
    }

    #[Route('', name: 'admin_site_settings_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $settings = $this->siteSettingsRepository->getSettings();
        $form = $this->createForm(SiteSettingsAdminType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($settings, $form, 'logoFile', $settings->setLogoUrl(...));
            $this->handleImageUpload($settings, $form, 'logoFooterFile', $settings->setLogoFooterUrl(...));
            $this->handleImageUpload($settings, $form, 'faviconFile', $settings->setFaviconUrl(...));

            $this->entityManager->flush();

            $this->addFlash('success', 'Paramètres enregistrés.');

            return $this->redirectToRoute('admin_site_settings_edit');
        }

        return $this->render('admin/site_settings/edit.html.twig', [
            'settings' => $settings,
            'form' => $form,
        ]);
    }

    /**
     * @param callable(?string): mixed $setUrl
     */
    private function handleImageUpload(object $settings, FormInterface $form, string $fieldName, callable $setUrl): void
    {
        $file = $form->get($fieldName)->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $setUrl($this->imageUploader->upload($file));
    }
}
