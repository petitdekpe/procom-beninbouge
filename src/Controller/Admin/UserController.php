<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EditorProfileProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private EditorProfileProvisioner $editorProfileProvisioner,
    ) {
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $this->userRepository->findBy([], ['id' => 'ASC']),
        ]);
    }

    #[Route('/{id}/roles', name: 'admin_user_update_roles', methods: ['POST'])]
    public function updateRoles(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_user_index');
        }

        $roles = ['ROLE_READER'];
        if ($request->request->getBoolean('editor')) {
            $roles[] = 'ROLE_EDITOR';
        }
        if ($request->request->getBoolean('editor_in_chief')) {
            $roles[] = 'ROLE_EDITOR_IN_CHIEF';
        }
        if ($request->request->getBoolean('proofreader')) {
            $roles[] = 'ROLE_PROOFREADER';
        }
        // Never let an admin strip their own ROLE_ADMIN from this screen —
        // the checkbox is disabled client-side for that row precisely
        // because a disabled checkbox submits nothing, which would
        // otherwise silently demote the very account making the request.
        if ($request->request->getBoolean('admin') || $user === $this->getUser()) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);

        // ROLE_EDITOR_IN_CHIEF and ROLE_PROOFREADER both imply ROLE_EDITOR
        // (see security.yaml's role_hierarchy) — a rédacteur en chef or
        // correcteur can also author their own articles, so they need the
        // byline too, even if the "editor" checkbox itself wasn't ticked.
        $impliesEditor = array_intersect(['ROLE_EDITOR', 'ROLE_EDITOR_IN_CHIEF', 'ROLE_PROOFREADER', 'ROLE_ADMIN'], $roles);
        if ([] !== $impliesEditor) {
            $this->editorProfileProvisioner->ensureProfile($user);
        }

        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Rôles mis à jour pour %s.', $user->getEmail()));

        return $this->redirectToRoute('admin_user_index');
    }
}
