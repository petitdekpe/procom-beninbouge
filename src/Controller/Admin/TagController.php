<?php

namespace App\Controller\Admin;

use App\Entity\Tag;
use App\Form\TagAdminType;
use App\Repository\TagRepository;
use App\Service\SeoMetadataGenerator;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/tags')]
final class TagController extends AbstractController
{
    public function __construct(
        private TagRepository $tagRepository,
        private EntityManagerInterface $entityManager,
        private SeoMetadataGenerator $seo,
    ) {
    }

    #[Route('', name: 'admin_tag_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/tag/index.html.twig', [
            'tags' => $this->tagRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_tag_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $tag = new Tag();
        $form = $this->createForm(TagAdminType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applySlugDefault($tag);

            $this->entityManager->persist($tag);
            $this->entityManager->flush();

            $this->addFlash('success', 'Mot-clé créé.');

            return $this->redirectToRoute('admin_tag_index');
        }

        return $this->render('admin/tag/new.html.twig', [
            'tag' => $tag,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_tag_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tag $tag): Response
    {
        $form = $this->createForm(TagAdminType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applySlugDefault($tag);

            $this->entityManager->flush();

            $this->addFlash('success', 'Mot-clé mis à jour.');

            return $this->redirectToRoute('admin_tag_index');
        }

        return $this->render('admin/tag/edit.html.twig', [
            'tag' => $tag,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_tag_delete', methods: ['POST'])]
    public function delete(Request $request, Tag $tag): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($tag);
                $this->entityManager->flush();

                $this->addFlash('success', 'Mot-clé supprimé.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', 'Impossible de supprimer : des articles ou médias utilisent encore ce mot-clé.');
            }
        }

        return $this->redirectToRoute('admin_tag_index');
    }

    private function applySlugDefault(Tag $tag): void
    {
        if ($tag->getSlug() || !$tag->getName()) {
            return;
        }

        $tag->setSlug($this->seo->uniqueSlug(
            $tag->getName(),
            fn (string $candidate): bool => $this->slugTakenByAnotherTag($candidate, $tag),
        ));
    }

    private function slugTakenByAnotherTag(string $candidate, Tag $tag): bool
    {
        $existing = $this->tagRepository->findOneBy(['slug' => $candidate]);

        return null !== $existing && $existing !== $tag;
    }
}
