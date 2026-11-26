<?php

namespace App\Controller\Api\Mobile;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Socle de l'API de l'application mobile (/api/mobile/v1). Réponses JSON ;
 * les erreurs ont la forme {"error": "…", "fields": {"champ": "…"}}.
 */
abstract class AbstractMobileController extends AbstractController
{
    protected function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        // Envoi avec fichier (multipart) : les champs sont en JSON dans « data ».
        $raw = $request->request->has('data') ? (string) $request->request->get('data') : $request->getContent();
        if ('' === $raw) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            throw new BadRequestHttpException('Données JSON invalides.');
        }

        return $data;
    }

    /** @param array<string, string> $fields */
    protected function error(string $message, int $status, array $fields = []): JsonResponse
    {
        return $this->json(['error' => $message] + ([] === $fields ? [] : ['fields' => $fields]), $status);
    }

    protected function violations(ConstraintViolationListInterface $violations): JsonResponse
    {
        $fields = [];
        foreach ($violations as $violation) {
            $fields[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        return $this->error(implode(' ', $fields), 422, $fields);
    }

    protected function formErrors(FormInterface $form): JsonResponse
    {
        $fields = [];
        foreach ($form->getErrors(true) as $error) {
            $name = $error->getOrigin()?->getName() ?? '';
            $fields[$name] ??= $error->getMessage();
        }

        return $this->error(implode(' ', $fields), 422, $fields);
    }

    protected function date(?\DateTimeInterface $date): ?string
    {
        return $date?->format(\DateTimeInterface::ATOM);
    }

    /**
     * Date ISO 8601 envoyée par le téléphone (avec son décalage horaire),
     * ramenée au fuseau du serveur, celui des dates en base.
     */
    protected function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        } catch (\Exception) {
            throw new BadRequestHttpException('Date invalide.');
        }
    }
}
