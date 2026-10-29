<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Entity\Enum\ActivityAction;
use App\Repository\ContactMessageRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Messages sent through the homepage "Nous joindre" form
 * (App\Controller\ContactController). Restricted to editors-in-chief and
 * admins since they carry personal contact details.
 */
#[IsGranted('ROLE_EDITOR_IN_CHIEF')]
#[Route('/admin/messages')]
final class ContactMessageController extends AbstractController
{
    public function __construct(
        private ContactMessageRepository $contactMessageRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'admin_contact_message_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $unreadOnly = 'all' !== $request->query->get('filter');

        return $this->render('admin/contact_message/index.html.twig', [
            'messages' => $this->contactMessageRepository->findForAdmin($unreadOnly),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $this->contactMessageRepository->countUnread(),
        ]);
    }

    #[Route('/{id}/toggle-read', name: 'admin_contact_message_toggle_read', methods: ['POST'])]
    public function toggleRead(Request $request, ContactMessage $message): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $message->setIsRead(!$message->isRead());
            $this->entityManager->flush();

            $this->addFlash('success', $message->isRead() ? 'Message marqué comme lu.' : 'Message marqué comme non lu.');
        }

        return $this->redirectToRoute('admin_contact_message_index', ['filter' => $request->query->get('filter')]);
    }

    #[Route('/{id}/delete', name: 'admin_contact_message_delete', methods: ['POST'])]
    public function delete(Request $request, ContactMessage $message): Response
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $id = $message->getId();
            $this->entityManager->remove($message);
            $this->entityManager->flush();
            $this->activityLogger->log(ActivityAction::Deletion, 'ContactMessage', $id);

            $this->addFlash('success', 'Message supprimé.');
        }

        return $this->redirectToRoute('admin_contact_message_index', ['filter' => $request->query->get('filter')]);
    }
}
