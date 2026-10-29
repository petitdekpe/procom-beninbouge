<?php

namespace App\Controller\Admin;

use App\Entity\Enum\ActivityAction;
use App\Entity\TeamMember;
use App\Form\TeamMemberAdminType;
use App\Repository\TeamMemberRepository;
use App\Service\ActivityLogger;
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
 * The « Notre équipe » block of the À propos page (templates/about/index.html.twig):
 * members are created, reordered and hidden/shown here.
 */
#[IsGranted('ROLE_EDITOR_IN_CHIEF')]
#[Route('/admin/team')]
final class TeamMemberController extends AbstractController
{
    public function __construct(
        private TeamMemberRepository $teamMemberRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploader $imageUploader,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'admin_team_member_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/team_member/index.html.twig', [
            'members' => $this->teamMemberRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_team_member_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $member = new TeamMember();
        $form = $this->createForm(TeamMemberAdminType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePhotoUpload($member, $form);
            $member->setPosition($this->teamMemberRepository->nextPosition());
            $this->entityManager->persist($member);
            $this->entityManager->flush();

            $this->addFlash('success', 'Membre ajouté à l\'équipe.');

            return $this->redirectToRoute('admin_team_member_index');
        }

        return $this->render('admin/team_member/new.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_team_member_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TeamMember $member): Response
    {
        $form = $this->createForm(TeamMemberAdminType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePhotoUpload($member, $form);
            $this->entityManager->flush();

            $this->addFlash('success', 'Membre mis à jour.');

            return $this->redirectToRoute('admin_team_member_index');
        }

        return $this->render('admin/team_member/edit.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_team_member_toggle', methods: ['POST'])]
    public function toggle(Request $request, TeamMember $member): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $member->setVisible(!$member->isVisible());
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_team_member_index');
    }

    #[Route('/{id}/move-up', name: 'admin_team_member_move_up', methods: ['POST'])]
    public function moveUp(Request $request, TeamMember $member): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->swapWithNeighbor($member, -1);
        }

        return $this->redirectToRoute('admin_team_member_index');
    }

    #[Route('/{id}/move-down', name: 'admin_team_member_move_down', methods: ['POST'])]
    public function moveDown(Request $request, TeamMember $member): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->swapWithNeighbor($member, 1);
        }

        return $this->redirectToRoute('admin_team_member_index');
    }

    #[Route('/{id}/delete', name: 'admin_team_member_delete', methods: ['POST'])]
    public function delete(Request $request, TeamMember $member): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $id = $member->getId();
            $this->entityManager->remove($member);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Deletion, 'TeamMember', $id);

            $this->addFlash('success', 'Membre retiré de l\'équipe.');
        }

        return $this->redirectToRoute('admin_team_member_index');
    }

    private function handlePhotoUpload(TeamMember $member, FormInterface $form): void
    {
        $file = $form->get('photoFile')->getData();
        if ($file instanceof UploadedFile) {
            $member->setPhoto($this->imageUploader->upload($file));
        }
    }

    /**
     * Same approach as HomeSectionController::swapWithNeighbor(). Positions
     * are renumbered 0..n-1 first so that members sharing a position (or
     * gaps left by deletions) can't make a move silently do nothing.
     */
    private function swapWithNeighbor(TeamMember $member, int $direction): void
    {
        $ordered = $this->teamMemberRepository->findAllOrdered();
        foreach ($ordered as $position => $item) {
            $item->setPosition($position);
        }

        $index = array_search($member, $ordered, true);
        $neighborIndex = $index + $direction;

        if (false !== $index && isset($ordered[$neighborIndex])) {
            $ordered[$neighborIndex]->setPosition($index);
            $member->setPosition($neighborIndex);
        }

        $this->entityManager->flush();
    }
}
