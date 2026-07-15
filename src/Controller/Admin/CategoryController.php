<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Enum\ActivityAction;
use App\Form\CategoryAdminType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogger;
use App\Service\SeoMetadataGenerator;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
        private SeoMetadataGenerator $seo,
        #[Autowire(service: 'cache.listings')]
        private CacheItemPoolInterface $listingsCache,
    ) {
    }

    #[Route('', name: 'admin_category_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $this->categoryRepository->findBy([], ['parent' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryAdminType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applySeoDefaults($category);

            $this->entityManager->persist($category);
            $this->entityManager->flush();
            $this->listingsCache->clear();

            $this->addFlash('success', 'Catégorie créée.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
    {
        $form = $this->createForm(CategoryAdminType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applySeoDefaults($category);

            $this->entityManager->flush();
            $this->listingsCache->clear();

            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $id = $category->getId();

            try {
                $this->entityManager->remove($category);
                $this->entityManager->flush();
                $this->listingsCache->clear();
                $this->activityLogger->log(ActivityAction::Deletion, 'Category', $id);

                $this->addFlash('success', 'Catégorie supprimée.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', 'Impossible de supprimer : des articles utilisent encore cette catégorie.');
            }
        }

        return $this->redirectToRoute('admin_category_index');
    }

    /**
     * Same "only fill when blank" reasoning as ArticleSaver::applyDefaults().
     */
    private function applySeoDefaults(Category $category): void
    {
        if (!$category->getMetaTitle() && $category->getName()) {
            $category->setMetaTitle($this->seo->metaTitle($category->getName()));
        }

        if (!$category->getMetaDescription()) {
            $source = $category->getDescription() ?: $category->getName();
            if ($source) {
                $category->setMetaDescription($this->seo->metaDescription($source));
            }
        }
    }
}
