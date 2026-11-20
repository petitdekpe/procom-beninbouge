<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Form\RoleAdminType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\Permission;
use App\Security\PermissionChecker;
use App\Service\SeoMetadataGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rôles et permissions : un rôle est un ensemble de permissions
 * (App\Security\Permission). Les quatre rôles système peuvent être réglés
 * mais pas supprimés ; ROLE_ADMIN garde toutes les permissions. On ne peut
 * accorder à un rôle que des permissions qu'on possède soi-même, pour qu'une
 * modification de rôle ne serve pas à s'élever au-dessus de ses propres droits.
 */
#[IsGranted('roles.manage')]
#[Route('/admin/roles')]
final class RoleController extends AbstractController
{
    private const RESERVED_CODES = ['ROLE_READER', 'ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'];

    public function __construct(
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private PermissionChecker $permissionChecker,
        private SeoMetadataGenerator $seo,
    ) {
    }

    #[Route('', name: 'admin_role_index', methods: ['GET'])]
    public function index(): Response
    {
        $roles = $this->roleRepository->findAllOrdered();

        $counts = [];
        foreach ($this->userRepository->findAll() as $user) {
            foreach ($user->getRoles() as $code) {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }

        return $this->render('admin/role/index.html.twig', [
            'roles' => $roles,
            'counts' => $counts,
            'groups' => Permission::grouped(),
        ]);
    }

    #[Route('/new', name: 'admin_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $role = new Role();
        $form = $this->createForm(RoleAdminType::class, $role, ['grantable_permissions' => $this->grantable()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role->setCode($this->uniqueCode((string) $role->getLabel()));
            $role->setPermissions(array_values(array_intersect($role->getPermissions(), $this->grantable())));

            $this->entityManager->persist($role);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Rôle « %s » créé. Vous pouvez maintenant l\'attribuer depuis la page Utilisateurs.', $role->getLabel()));

            return $this->redirectToRoute('admin_role_index');
        }

        return $this->render('admin/role/new.html.twig', ['form' => $form], $this->formResponse($form));
    }

    #[Route('/{id}/edit', name: 'admin_role_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Role $role): Response
    {
        $before = $role->getPermissions();
        $grantable = $this->grantable();

        $form = $this->createForm(RoleAdminType::class, $role, [
            'grantable_permissions' => $grantable,
            'lock_permissions' => $role->isAdmin(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$role->isAdmin()) {
                // Les permissions que l'on ne détient pas soi-même restent telles quelles.
                $locked = array_diff($before, $grantable);
                $role->setPermissions(array_values(array_unique(array_merge(array_intersect($role->getPermissions(), $grantable), $locked))));
            }

            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Rôle « %s » mis à jour. Les permissions s\'appliquent immédiatement.', $role->getLabel()));

            return $this->redirectToRoute('admin_role_index');
        }

        return $this->render('admin/role/edit.html.twig', [
            'form' => $form,
            'role' => $role,
        ], $this->formResponse($form));
    }

    #[Route('/{id}/delete', name: 'admin_role_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Role $role): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_role_index');
        }

        if ($role->isSystem()) {
            $this->addFlash('error', 'Les rôles système ne peuvent pas être supprimés.');

            return $this->redirectToRoute('admin_role_index');
        }

        // Retire le rôle supprimé des comptes qui le portaient.
        $affected = 0;
        foreach ($this->userRepository->findAll() as $user) {
            if (\in_array($role->getCode(), $user->getRoles(), true)) {
                $user->setRoles(array_values(array_diff($user->getRoles(), [$role->getCode(), 'ROLE_READER'])));
                ++$affected;
            }
        }

        $label = $role->getLabel();
        $this->entityManager->remove($role);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Rôle « %s » supprimé%s.', $label, $affected > 0 ? sprintf(' (retiré de %d compte%s)', $affected, $affected > 1 ? 's' : '') : ''));

        return $this->redirectToRoute('admin_role_index');
    }

    /**
     * Permissions que l'utilisateur connecté peut accorder : toutes pour un
     * administrateur, sinon les siennes.
     *
     * @return list<string>
     */
    private function grantable(): array
    {
        $actor = $this->getUser();

        return $actor instanceof User ? $this->permissionChecker->permissionsOf($actor) : [];
    }

    private function uniqueCode(string $label): string
    {
        $base = 'ROLE_'.strtoupper(str_replace('-', '_', $this->seo->slugify($label)));
        $base = rtrim(substr($base, 0, 50), '_');
        if ('ROLE_' === $base) {
            $base = 'ROLE_PERSONNALISE';
        }

        $code = $base;
        for ($i = 2; \in_array($code, self::RESERVED_CODES, true) || null !== $this->roleRepository->findOneByCode($code); ++$i) {
            $code = $base.'_'.$i;
        }

        return $code;
    }

    private function formResponse(\Symfony\Component\Form\FormInterface $form): Response
    {
        return new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
