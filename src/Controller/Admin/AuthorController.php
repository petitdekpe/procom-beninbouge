<?php

namespace App\Controller\Admin;

use App\Entity\Author;
use App\Entity\User;
use App\Form\AuthorAdminType;
use App\Repository\AuthorRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/authors')]
final class AuthorController extends AbstractController
{
    public function __construct(
        private AuthorRepository $authorRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_author_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/author/index.html.twig', [
            'authors' => $this->authorRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_author_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $author = new Author();
        $form = $this->createForm(AuthorAdminType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($author);
            $this->entityManager->flush();

            $this->addFlash('success', 'Auteur créé.');

            return $this->redirectToRoute('admin_author_index');
        }

        return $this->render('admin/author/new.html.twig', [
            'author' => $author,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_author_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Author $author): Response
    {
        if ($author->getUser() instanceof User && !$this->isGranted('ROLE_ADMIN')) {
            if ($author->getUser() === $this->getUser()) {
                return $this->redirectToRoute('admin_profile');
            }

            throw $this->createAccessDeniedException("Le profil d'un éditeur ne peut être modifié que via une demande de validation.");
        }

        $form = $this->createForm(AuthorAdminType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Auteur mis à jour.');

            return $this->redirectToRoute('admin_author_index');
        }

        return $this->render('admin/author/edit.html.twig', [
            'author' => $author,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_author_delete', methods: ['POST'])]
    public function delete(Request $request, Author $author): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($author);
                $this->entityManager->flush();

                $this->addFlash('success', 'Auteur supprimé.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', 'Impossible de supprimer : des articles utilisent encore cet auteur.');
            }
        }

        return $this->redirectToRoute('admin_author_index');
    }
}
