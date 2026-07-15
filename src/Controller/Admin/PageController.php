<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use App\Form\PageAdminType;
use App\Repository\PageRepository;
use App\Service\SeoMetadataGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/pages')]
final class PageController extends AbstractController
{
    public function __construct(
        private PageRepository $pageRepository,
        private EntityManagerInterface $entityManager,
        private SeoMetadataGenerator $seo,
    ) {
    }

    #[Route('', name: 'admin_page_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/page/index.html.twig', [
            'pages' => $this->pageRepository->findBy([], ['position' => 'ASC', 'title' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_page_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $page = new Page();
        $form = $this->createForm(PageAdminType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyDefaults($page);

            $this->entityManager->persist($page);
            $this->entityManager->flush();

            $this->addFlash('success', 'Page créée.');

            return $this->redirectToRoute('admin_page_index');
        }

        return $this->render('admin/page/new.html.twig', [
            'page' => $page,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_page_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Page $page): Response
    {
        $form = $this->createForm(PageAdminType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyDefaults($page);

            $this->entityManager->flush();

            $this->addFlash('success', 'Page mise à jour.');

            return $this->redirectToRoute('admin_page_index');
        }

        return $this->render('admin/page/edit.html.twig', [
            'page' => $page,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_page_delete', methods: ['POST'])]
    public function delete(Request $request, Page $page): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->entityManager->remove($page);
            $this->entityManager->flush();

            $this->addFlash('success', 'Page supprimée.');
        }

        return $this->redirectToRoute('admin_page_index');
    }

    private function applyDefaults(Page $page): void
    {
        if (!$page->getSlug() && $page->getTitle()) {
            $page->setSlug($this->seo->uniqueSlug(
                $page->getTitle(),
                fn (string $candidate): bool => $this->slugTakenByAnotherPage($candidate, $page),
            ));
        }

        if (!$page->getMetaTitle() && $page->getTitle()) {
            $page->setMetaTitle($this->seo->metaTitle($page->getTitle()));
        }

        if (!$page->getMetaDescription() && $page->getContent()) {
            $page->setMetaDescription($this->seo->metaDescription($page->getContent()));
        }
    }

    private function slugTakenByAnotherPage(string $candidate, Page $page): bool
    {
        $existing = $this->pageRepository->findOneBy(['slug' => $candidate]);

        return null !== $existing && $existing !== $page;
    }
}
