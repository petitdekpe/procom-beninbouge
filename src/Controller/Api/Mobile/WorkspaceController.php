<?php

namespace App\Controller\Api\Mobile;

use App\Entity\Article;
use App\Entity\Enum\ArticleStatus;
use App\Repository\AnnouncementRepository;
use App\Repository\ArticleRepository;
use App\Service\ImageUploader;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Liste « À traiter » (ce qui attend la personne connectée, selon ses
 * droits) et envoi des images de l'éditeur.
 */
#[Route('/api/mobile/v1')]
final class WorkspaceController extends AbstractMobileController
{
    private const LIMIT = 20;

    #[Route('/inbox', name: 'mobile_inbox', methods: ['GET'])]
    public function inbox(ArticleRepository $articles, AnnouncementRepository $announcements): JsonResponse
    {
        $sections = [];

        $queue = function (string $key, string $title, ArticleStatus $status) use ($articles, &$sections): void {
            $this->addArticles($sections, $key, $title, $articles->findBy(['status' => $status], ['updatedAt' => 'ASC'], self::LIMIT), $articles->count(['status' => $status]));
        };
        if ($this->isGranted('workflow.review')) {
            $queue('review', 'Articles à relire', ArticleStatus::InReview);
        }
        if ($this->isGranted('workflow.proofread')) {
            $queue('correction', 'Articles à corriger', ArticleStatus::InCorrection);
        }

        $author = $this->currentUser()->getAuthor();
        if (null !== $author && $this->isGranted('articles.write')) {
            $returned = $articles->createQueryBuilder('a')
                ->andWhere('a.author = :author AND a.status = :draft AND a.rejectionReason IS NOT NULL')
                ->setParameter('author', $author)
                ->setParameter('draft', ArticleStatus::Draft)
                ->orderBy('a.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
            $this->addArticles($sections, 'returned', 'Mes articles renvoyés', \array_slice($returned, 0, self::LIMIT), \count($returned));
        }

        if ($this->isGranted('announcements.manage')) {
            $pending = $announcements->findForAdmin('review');
            $sections[] = [
                'key' => 'announcements',
                'title' => 'Annonces à valider',
                'count' => \count($pending),
                'items' => array_map(fn ($a) => [
                    'type' => 'announcement',
                    'id' => $a->getId(),
                    'title' => $a->getTitle(),
                    'subtitle' => $a->getOrganization().($a->isReviewOverdue() ? ' · délai de 48 h dépassé' : ''),
                    'urgent' => $a->isReviewOverdue(),
                ], \array_slice($pending, 0, self::LIMIT)),
            ];
        }

        return $this->json(['sections' => $sections]);
    }

    /** Image de couverture ou image insérée dans le texte. */
    #[Route('/uploads/images', name: 'mobile_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request, ImageUploader $uploader, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('admin.access');

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return $this->error('Aucun fichier reçu.', 400);
        }

        $violations = $validator->validate($file, new File(
            maxSize: '5M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
        ));
        if (\count($violations) > 0) {
            return $this->error((string) $violations[0]->getMessage(), 422);
        }

        try {
            $url = $uploader->upload($file);
        } catch (FileException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->json(['url' => $url], 201);
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param list<Article>              $articles
     */
    private function addArticles(array &$sections, string $key, string $title, array $articles, int $count): void
    {
        $sections[] = [
            'key' => $key,
            'title' => $title,
            'count' => $count,
            'items' => array_map(static fn (Article $article) => [
                'type' => 'article',
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'subtitle' => $article->getRejectionReason() ?? $article->getCategory()?->getName(),
                'urgent' => false,
            ], $articles),
        ];
    }
}
