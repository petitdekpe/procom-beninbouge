<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\Permission;
use App\Security\PermissionChecker;
use App\Service\EditorProfileProvisioner;
use App\Service\TemporaryPasswordMailer;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Comptes de l'administration : création (mot de passe temporaire envoyé par
 * e-mail), rôles, activation/désactivation, réinitialisation du mot de passe.
 *
 * Garde-fous : on n'attribue que des rôles dont les permissions sont déjà
 * celles de l'auteur de la modification (pas d'élévation de privilèges) ;
 * seul un administrateur touche aux administrateurs ; on ne se désactive,
 * ne se supprime ni ne se retire ses droits admin soi-même ; il reste
 * toujours au moins un administrateur actif.
 */
#[IsGranted('users.manage')]
#[Route('/admin/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager,
        private EditorProfileProvisioner $editorProfileProvisioner,
        private TemporaryPasswordMailer $temporaryPasswordMailer,
        private PermissionChecker $permissionChecker,
    ) {
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        $labels = [];
        foreach ($this->roleRepository->findAll() as $role) {
            $labels[(string) $role->getCode()] = (string) $role->getLabel();
        }

        return $this->render('admin/user/index.html.twig', [
            'users' => $this->userRepository->findBy([], ['id' => 'ASC']),
            'roleLabels' => $labels,
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserAdminType::class, $user, [
            'assignable_roles' => $this->assignableRoles(),
            'allow_enabled' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mot de passe provisoire : rempli ici pour satisfaire la colonne, remplacé par issue().
            $user->setPassword('!');
            $result = $this->temporaryPasswordMailer->issue($user, true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
            // Après l'enregistrement du compte : le profil auteur s'y rattache.
            $this->syncAuthorProfile($user);

            $this->flashDelivery($user, $result, true);

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', ['form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, User $user): Response
    {
        $this->denyUnlessCanManage($user);

        $originalRoles = $user->getRoles();
        $assignable = $this->assignableRoles();
        $isSelf = $user === $this->getUser();

        $form = $this->createForm(UserAdminType::class, $user, [
            'assignable_roles' => $assignable,
            'allow_enabled' => !$isSelf,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Les rôles que l'auteur de la modification n'a pas le droit d'attribuer ne bougent pas.
            $assignableCodes = array_map(static fn (Role $r) => (string) $r->getCode(), $assignable);
            $kept = array_diff($originalRoles, $assignableCodes, ['ROLE_READER']);
            $user->setRoles(array_values(array_unique(array_merge(array_diff($user->getRoles(), ['ROLE_READER']), $kept))));

            if ($isSelf && \in_array(Role::ADMIN, $originalRoles, true) && !\in_array(Role::ADMIN, $user->getRoles(), true)) {
                $user->setRoles(array_merge($user->getRoles(), [Role::ADMIN]));
                $this->addFlash('error', 'Vous ne pouvez pas retirer votre propre rôle d\'administrateur.');
            }

            if ($this->wouldLeaveNoAdmin($user, $originalRoles)) {
                $form->addError(new FormError('Il doit rester au moins un administrateur actif.'));
            } else {
                $this->syncAuthorProfile($user);
                $this->entityManager->flush();
                $this->addFlash('success', sprintf('Compte %s mis à jour.', $user->getEmail()));

                return $this->redirectToRoute('admin_user_index');
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form,
            'target' => $user,
        ], $this->formResponse($form));
    }

    #[Route('/{id}/reset-password', name: 'admin_user_reset_password', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resetPassword(Request $request, User $user): Response
    {
        $this->denyUnlessCanManage($user);

        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $result = $this->temporaryPasswordMailer->issue($user, false);
            $this->entityManager->flush();
            $this->flashDelivery($user, $result, false);
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/toggle', name: 'admin_user_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, User $user): Response
    {
        $this->denyUnlessCanManage($user);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_user_index');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        } elseif ($user->isEnabled() && $this->wouldLeaveNoAdmin($user, $user->getRoles(), disabling: true)) {
            $this->addFlash('error', 'Il doit rester au moins un administrateur actif.');
        } else {
            $user->setEnabled(!$user->isEnabled());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Compte %s %s.', $user->getEmail(), $user->isEnabled() ? 'réactivé' : 'désactivé : il ne peut plus se connecter'));
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user): Response
    {
        $this->denyUnlessCanManage($user);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_user_index');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('admin_user_index');
        }

        if ($this->wouldLeaveNoAdmin($user, $user->getRoles(), disabling: true)) {
            $this->addFlash('error', 'Il doit rester au moins un administrateur actif.');

            return $this->redirectToRoute('admin_user_index');
        }

        try {
            $email = $user->getEmail();
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Compte %s supprimé.', $email));
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'Ce compte est lié à du contenu (articles, validations…) et ne peut pas être supprimé : désactivez-le plutôt.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * Rôles que l'utilisateur connecté a le droit d'attribuer : tous pour un
     * administrateur, sinon ceux dont les permissions sont déjà les siennes.
     *
     * @return list<Role>
     */
    private function assignableRoles(): array
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return [];
        }

        $own = $this->permissionChecker->permissionsOf($actor);
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        return array_values(array_filter(
            $this->roleRepository->findAllOrdered(),
            static fn (Role $role): bool => $isAdmin || (!$role->isAdmin() && [] === array_diff($role->getPermissions(), $own)),
        ));
    }

    private function denyUnlessCanManage(User $target): void
    {
        if (\in_array(Role::ADMIN, $target->getRoles(), true) && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Seul un administrateur peut modifier un administrateur.');
        }
    }

    /**
     * Vrai si, après cette modification (ou cette désactivation / suppression),
     * plus aucun administrateur actif ne resterait.
     *
     * @param list<string> $rolesBefore
     */
    private function wouldLeaveNoAdmin(User $user, array $rolesBefore, bool $disabling = false): bool
    {
        $wasActiveAdmin = \in_array(Role::ADMIN, $rolesBefore, true);
        if (!$wasActiveAdmin) {
            return false;
        }

        $stillActiveAdmin = !$disabling && $user->isEnabled() && \in_array(Role::ADMIN, $user->getRoles(), true);
        if ($stillActiveAdmin) {
            return false;
        }

        foreach ($this->userRepository->findAll() as $other) {
            if ($other !== $user && $other->isEnabled() && \in_array(Role::ADMIN, $other->getRoles(), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Quiconque peut écrire des articles a besoin de son profil auteur
     * (signature) : il est créé dès que l'un de ses rôles le permet.
     */
    private function syncAuthorProfile(User $user): void
    {
        if ($this->permissionChecker->rolesGrant($user->getRoles(), Permission::ArticlesWrite)) {
            $this->editorProfileProvisioner->ensureProfile($user);
        }
    }

    /**
     * @param array{sent: bool, error: ?string, devPassword: ?string} $result
     */
    private function flashDelivery(User $user, array $result, bool $created): void
    {
        $subject = $created ? sprintf('Compte %s créé', $user->getEmail()) : sprintf('Nouveau mot de passe temporaire généré pour %s', $user->getEmail());

        if ($result['sent']) {
            $this->addFlash('success', sprintf('%s : le mot de passe temporaire lui a été envoyé par e-mail.', $subject));

            return;
        }

        $this->addFlash('error', sprintf('%s, mais l\'e-mail n\'a pas pu être envoyé (%s). Utilisez « Renvoyer un mot de passe » une fois l\'envoi d\'e-mails configuré.', $subject, $result['error'] ?? 'erreur inconnue'));

        if (null !== $result['devPassword']) {
            $this->addFlash('success', sprintf('Mode développement : mot de passe temporaire de %s = %s', $user->getEmail(), $result['devPassword']));
        }
    }

    private function formResponse(\Symfony\Component\Form\FormInterface $form): Response
    {
        return new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
