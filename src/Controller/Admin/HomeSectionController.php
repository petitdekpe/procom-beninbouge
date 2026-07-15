<?php

namespace App\Controller\Admin;

use App\Entity\HomeSection;
use App\Repository\CategoryRepository;
use App\Repository\HomeSectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/home-sections')]
final class HomeSectionController extends AbstractController
{
    public function __construct(
        private HomeSectionRepository $homeSectionRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_home_section_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/home_section/index.html.twig', [
            'sections' => $this->homeSectionRepository->findAllOrdered(),
            'categories' => $this->categoryRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_home_section_toggle', methods: ['POST'])]
    public function toggle(Request $request, HomeSection $section): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $section->setEnabled(!$section->isEnabled());
            $this->entityManager->flush();

            $this->addFlash('success', $section->isEnabled() ? 'Section activée.' : 'Section désactivée.');
        }

        return $this->redirectToRoute('admin_home_section_index');
    }

    #[Route('/{id}/category', name: 'admin_home_section_category', methods: ['POST'])]
    public function setCategory(Request $request, HomeSection $section): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $categoryId = $request->request->get('category_id');
            $category = $categoryId ? $this->categoryRepository->find($categoryId) : null;
            $section->setCategory($category);
            $this->entityManager->flush();

            $this->addFlash('success', $category ? 'Rubrique mise à jour.' : 'Rubrique retirée de la section.');
        }

        return $this->redirectToRoute('admin_home_section_index');
    }

    #[Route('/{id}/move-up', name: 'admin_home_section_move_up', methods: ['POST'])]
    public function moveUp(Request $request, HomeSection $section): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->swapWithNeighbor($section, -1);
        }

        return $this->redirectToRoute('admin_home_section_index');
    }

    #[Route('/{id}/move-down', name: 'admin_home_section_move_down', methods: ['POST'])]
    public function moveDown(Request $request, HomeSection $section): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->swapWithNeighbor($section, 1);
        }

        return $this->redirectToRoute('admin_home_section_index');
    }

    /**
     * Swaps $section's position with the section immediately before it
     * ($direction -1) or after it ($direction 1) in the current order —
     * the simplest way to reorder a short fixed list without a drag/drop
     * dependency.
     */
    private function swapWithNeighbor(HomeSection $section, int $direction): void
    {
        $ordered = $this->homeSectionRepository->findAllOrdered();
        $index = array_search($section, $ordered, true);
        $neighborIndex = $index + $direction;

        if (false === $index || !isset($ordered[$neighborIndex])) {
            return;
        }

        $neighbor = $ordered[$neighborIndex];
        $sectionPosition = $section->getPosition();
        $section->setPosition($neighbor->getPosition());
        $neighbor->setPosition($sectionPosition);

        $this->entityManager->flush();
    }
}
