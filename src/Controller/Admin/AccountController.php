<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\PasswordChangeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Mon compte » : changement de son propre mot de passe. Ouvert à tout
 * compte connecté, quels que soient ses rôles : c'est aussi la page où
 * l'on arrive à la première connexion avec un mot de passe temporaire
 * (App\EventSubscriber\PasswordChangeSubscriber).
 */
#[IsGranted('IS_AUTHENTICATED')]
#[Route('/admin/compte')]
final class AccountController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('/mot-de-passe', name: 'admin_account_password', methods: ['GET', 'POST'])]
    public function password(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(PasswordChangeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = (string) $form->get('newPassword')->getData();

            if ($this->hasher->isPasswordValid($user, $newPassword)) {
                $form->get('newPassword')->get('first')->addError(new \Symfony\Component\Form\FormError('Choisissez un mot de passe différent de l\'actuel.'));
            } else {
                $wasTemporary = $user->isMustChangePassword();
                $user->setPassword($this->hasher->hashPassword($user, $newPassword));
                $user->setMustChangePassword(false);
                $this->entityManager->flush();

                // La connexion repose sur l'authentification HTTP du navigateur :
                // il redemandera les identifiants avec le nouveau mot de passe.
                $this->addFlash('success', $wasTemporary
                    ? 'Mot de passe enregistré, bienvenue ! Si le navigateur vous redemande vos identifiants, utilisez le nouveau mot de passe.'
                    : 'Mot de passe modifié. Si le navigateur vous redemande vos identifiants, utilisez le nouveau mot de passe.');

                return $this->redirectToRoute('admin_dashboard');
            }
        }

        return $this->render('admin/account/password.html.twig', [
            'form' => $form,
            'temporary' => $user->isMustChangePassword(),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
