<?php

namespace App\Controller;

use App\Contact\CountryCallingCodes;
use App\Entity\ContactMessage;
use App\Entity\Enum\ContactSubject;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Public endpoints behind the homepage "Nous joindre" section
 * (templates/partials/home_sections/_contact.html.twig) and the footer
 * newsletter form (templates/partials/_footer.html.twig). Both are plain
 * HTML forms (no Form component) so the footer stays self-sufficient on
 * every page; each is protected by a CSRF token and a hidden "website"
 * honeypot field that bots fill in and humans never see. Results are
 * reported through dedicated flash keys (contact_*, newsletter_*) so they
 * don't collide with the article page's success/error flashes.
 */
final class ContactController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private NewsletterSubscriberRepository $newsletterSubscriberRepository,
    ) {
    }

    #[Route('/contact', name: 'app_contact_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $redirect = $this->redirect($this->generateUrl('app_home').'#contact');

        if (!$this->isCsrfTokenValid('contact', (string) $request->request->get('_token'))) {
            $this->addFlash('contact_error', 'Votre session a expiré. Merci de renvoyer le formulaire.');

            return $redirect;
        }

        if ('' !== (string) $request->request->get('website')) {
            $this->addFlash('contact_success', 'Message envoyé ! Nous vous répondrons dans les plus brefs délais.');

            return $redirect;
        }

        $countryCode = (string) $request->request->get('countryCode', CountryCallingCodes::DEFAULT);
        $digits = preg_replace('/\D+/', '', (string) $request->request->get('phone'));
        $email = trim((string) $request->request->get('email'));

        $message = (new ContactMessage())
            ->setFullName(trim((string) $request->request->get('fullName')))
            ->setPhone(CountryCallingCodes::isValid($countryCode) && '' !== $digits ? $countryCode.$digits : $digits)
            ->setEmail('' !== $email ? $email : null)
            ->setSubject(ContactSubject::tryFrom((string) $request->request->get('subject')))
            ->setMessage(trim((string) $request->request->get('message')));

        $errors = [];
        foreach ($this->validator->validate($message) as $violation) {
            $errors[$violation->getPropertyPath()] ??= $violation->getMessage();
        }
        if (!CountryCallingCodes::isValid($countryCode)) {
            $errors['phone'] ??= "L'indicatif pays est invalide.";
        }

        if ([] !== $errors) {
            $this->addFlash('contact_errors', $errors);
            $this->addFlash('contact_old', [
                'fullName' => $message->getFullName(),
                'countryCode' => $countryCode,
                'phone' => $digits,
                'email' => $email,
                'subject' => (string) $request->request->get('subject'),
                'message' => $message->getMessage(),
            ]);

            return $redirect;
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->addFlash('contact_success', 'Message envoyé ! Nous vous répondrons dans les plus brefs délais.');

        return $redirect;
    }

    #[Route('/newsletter', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): Response
    {
        $redirect = $this->redirectBack($request, 'newsletter');

        if (!$this->isCsrfTokenValid('newsletter', (string) $request->request->get('_token'))) {
            $this->addFlash('newsletter_error', 'Votre session a expiré. Merci de réessayer.');

            return $redirect;
        }

        if ('' !== (string) $request->request->get('website')) {
            $this->addFlash('newsletter_success', 'Merci pour votre inscription à la newsletter !');

            return $redirect;
        }

        $subscriber = (new NewsletterSubscriber())->setEmail((string) $request->request->get('email'));

        if (null !== $this->newsletterSubscriberRepository->findOneBy(['email' => $subscriber->getEmail()])) {
            $this->addFlash('newsletter_info', 'Vous êtes déjà inscrit à la newsletter.');

            return $redirect;
        }

        $violations = $this->validator->validate($subscriber);
        if (\count($violations) > 0) {
            $this->addFlash('newsletter_error', (string) $violations[0]->getMessage());

            return $redirect;
        }

        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $this->addFlash('newsletter_success', 'Merci pour votre inscription à la newsletter !');

        return $redirect;
    }

    /**
     * Back to the page the form was submitted from (same host only, so the
     * Referer can't be used as an open redirect), scrolled to $anchor.
     */
    private function redirectBack(Request $request, string $anchor): RedirectResponse
    {
        $referer = (string) $request->headers->get('referer');
        $target = $this->generateUrl('app_home');

        if ('' !== $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()) {
            $target = strtok($referer, '#');
        }

        return $this->redirect($target.'#'.$anchor);
    }
}
