<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\EditorProfileRequestType;
use App\Service\EditorProfileProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Mon profil" — every editor's self-service page for their own byline
 * identity. Changes are never applied directly: they are stored on
 * Author::$pending* and only take effect once an admin approves them from
 * the Validations queue (App\Controller\Admin\ValidationController).
 */
#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/profile')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private EditorProfileProvisioner $editorProfileProvisioner,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $author = $this->editorProfileProvisioner->ensureProfile($user);

        $form = $this->createForm(EditorProfileRequestType::class, [
            'firstName' => $author->getPendingFirstName() ?? $author->getFirstName(),
            'lastName' => $author->getPendingLastName() ?? $author->getLastName(),
            'email' => $author->getPendingEmail() ?? $author->getEmail(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $unchanged = $data['firstName'] === $author->getFirstName()
                && $data['lastName'] === $author->getLastName()
                && $data['email'] === $author->getEmail();

            if ($unchanged) {
                $author->clearPendingChanges();
                $this->addFlash('success', 'Profil inchangé.');
            } else {
                $author->setPendingFirstName($data['firstName']);
                $author->setPendingLastName($data['lastName']);
                $author->setPendingEmail($data['email']);
                $this->addFlash('success', 'Votre demande de modification a été envoyée et sera examinée par un administrateur.');
            }

            $this->entityManager->flush();

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile/edit.html.twig', [
            'author' => $author,
            'form' => $form,
        ]);
    }

    #[Route('/cancel-request', name: 'admin_profile_cancel_request', methods: ['POST'])]
    public function cancelRequest(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $author = $this->editorProfileProvisioner->ensureProfile($user);
            $author->clearPendingChanges();
            $this->entityManager->flush();
            $this->addFlash('success', 'Demande de modification annulée.');
        }

        return $this->redirectToRoute('admin_profile');
    }
}
