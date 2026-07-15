<?php

namespace App\Service;

use App\Entity\Author;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ensures every ROLE_EDITOR user has an Author "byline" profile linked to
 * their account (Author::$user), creating one on first use if the role was
 * granted before this feature existed, or via the raw API. The initial
 * values are a best-effort split of displayName — never the validation
 * gate, since nothing established is being overwritten yet; the editor
 * proposes a correction afterwards through the normal pending-change flow
 * (App\Controller\Admin\ProfileController) if needed.
 */
final class EditorProfileProvisioner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureProfile(User $user): Author
    {
        $author = $user->getAuthor();
        if (null !== $author) {
            return $author;
        }

        [$firstName, $lastName] = $this->splitDisplayName($user->getDisplayName() ?? '');

        $author = new Author();
        $author->setUser($user);
        $author->setFirstName($firstName);
        $author->setLastName($lastName);
        $author->setEmail($user->getEmail() ?? '');

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return $author;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitDisplayName(string $displayName): array
    {
        $parts = preg_split('/\s+/', trim($displayName), 2) ?: [];
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? $first;

        return [$first, $last];
    }
}
