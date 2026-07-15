<?php

namespace App\Controller\Admin;

use App\Service\ImageUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Backs the rich-text editor's image button (see assets/controllers/rich_text_controller.js):
 * the editor uploads the picked file here and embeds the returned URL inline,
 * reusing the same ImageUploader as Article cover images and Media files.
 */
#[IsGranted('ROLE_EDITOR')]
final class EditorImageController extends AbstractController
{
    public function __construct(
        private ImageUploader $imageUploader,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/admin/editor/upload-image', name: 'admin_editor_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], 403);
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 400);
        }

        $violations = $this->validator->validate($file, new File(
            maxSize: '5M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
        ));

        if (\count($violations) > 0) {
            return $this->json(['error' => $violations[0]->getMessage()], 422);
        }

        return $this->json(['url' => $this->imageUploader->upload($file)]);
    }
}
