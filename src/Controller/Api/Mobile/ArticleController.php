<?php

namespace App\Controller\Api\Mobile;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Enum\MediaType;
use App\Entity\Media;
use App\Entity\Tag;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Security\Mobile\ArticleAccess;
use App\Service\ArticleSaver;
use App\Service\EditorProfileProvisioner;
use App\Service\Mobile\HtmlContentPolicy;
use App\Service\Mobile\RequestKeys;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Articles dans l'application mobile : listes, rédaction et circuit
 * Rédaction → Relecture → Correction → Publication. Les transitions passent
 * par App\Service\ArticleSaver, comme celles du back-office ; les droits
 * sont ceux d'App\Security\Mobile\ArticleAccess.
 */
#[Route('/api/mobile/v1/articles')]
final class ArticleController extends AbstractMobileController
{
    private const PER_PAGE = 20;

    public function __construct(
        private ArticleRepository $articleRepository,
        private ArticleSaver $articleSaver,
        private ArticleAccess $access,
        private HtmlContentPolicy $contentPolicy,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'mobile_article_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->requireBrowse();

        $scope = (string) $request->query->get('scope', 'mine');
        $page = max(1, $request->query->getInt('page', 1));
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->orderBy('a.updatedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE + 1);

        switch ($scope) {
            case 'review':
                $this->denyAccessUnlessGranted('workflow.review');
                $qb->andWhere('a.status = :status')->setParameter('status', ArticleStatus::InReview);
                break;
            case 'correction':
                $this->denyAccessUnlessGranted('workflow.proofread');
                $qb->andWhere('a.status = :status')->setParameter('status', ArticleStatus::InCorrection);
                break;
            case 'all':
                break;
            default:
                $author = $this->currentUser()->getAuthor();
                if (null === $author) {
                    return $this->json(['items' => [], 'hasMore' => false]);
                }
                $qb->andWhere('a.author = :author')->setParameter('author', $author);
        }

        $search = trim((string) $request->query->get('q'));
        if ('' !== $search) {
            $qb->andWhere('a.title LIKE :search')->setParameter('search', '%'.addcslashes($search, '%_\\').'%');
        }

        $articles = $qb->getQuery()->getResult();

        return $this->json([
            'items' => array_map($this->summary(...), \array_slice($articles, 0, self::PER_PAGE)),
            'hasMore' => \count($articles) > self::PER_PAGE,
        ]);
    }

    #[Route('/{id}', name: 'mobile_article_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $this->requireBrowse();

        return $this->json(['article' => $this->detail($this->find($id))]);
    }

    #[Route('', name: 'mobile_article_create', methods: ['POST'])]
    public function create(Request $request, RequestKeys $keys, EditorProfileProvisioner $profiles): JsonResponse
    {
        $this->denyAccessUnlessGranted('articles.write');

        $user = $this->currentUser();
        $data = $this->payload($request);
        $clientId = (string) ($data['clientId'] ?? '');

        $existing = $keys->existing($user, $clientId, 'article', Article::class);
        if (null !== $existing) {
            return $this->json(['article' => $this->detail($existing), 'alreadyCreated' => true]);
        }

        // Toujours sous la signature de la personne connectée, toujours en brouillon.
        $article = (new Article())->setAuthor($profiles->ensureProfile($user));

        $failure = $this->apply($article, $data);
        if (null !== $failure) {
            return $failure;
        }

        $this->entityManager->wrapInTransaction(function () use ($article, $keys, $user, $clientId): void {
            $this->persist($article);
            $keys->remember($user, $clientId, 'article', (int) $article->getId());
        });

        return $this->json(['article' => $this->detail($article)], 201);
    }

    #[Route('/{id}', name: 'mobile_article_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireBrowse();
        $article = $this->find($id);
        $data = $this->payload($request);

        if (($data['version'] ?? null) !== $this->version($article)) {
            // La même modification a peut-être déjà été enregistrée, mais la réponse s'est perdue.
            if ($this->matches($article, $data)) {
                return $this->json(['article' => $this->detail($article)]);
            }

            return $this->conflict($article);
        }

        if (!$this->access->canEdit($article)) {
            return $this->error('Vous ne pouvez pas modifier cet article à cette étape du circuit.', 403);
        }

        $failure = $this->apply($article, $data);
        if (null !== $failure) {
            return $failure;
        }

        $this->persist($article);

        return $this->json(['article' => $this->detail($article)]);
    }

    #[Route('/{id}/actions/{action}', name: 'mobile_article_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => '[a-z_]+'])]
    public function action(Request $request, int $id, string $action): JsonResponse
    {
        $this->requireBrowse();
        $article = $this->find($id);
        $data = $this->payload($request);

        // On ne valide que la version qu'on a réellement lue.
        if (($data['version'] ?? null) !== $this->version($article)) {
            return $this->conflict($article);
        }
        if (!\in_array($action, $this->access->actions($article), true)) {
            return $this->error('Cette action n\'est pas disponible pour cet article.', 403);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if (\in_array($action, ['reject_review', 'reject_correction'], true) && '' === $reason) {
            return $this->error('Un motif de refus est obligatoire.', 422, ['reason' => 'Un motif de refus est obligatoire.']);
        }

        $publishAt = null;
        if ('schedule' === $action) {
            $publishAt = $this->parseDate($data['publishAt'] ?? null);
            if (null === $publishAt || $publishAt <= new \DateTimeImmutable()) {
                return $this->error('Choisissez une date de publication dans le futur.', 422);
            }
        }

        if (\in_array($action, ['submit', 'publish', 'schedule'], true) && (!$article->getTitle() || !$article->getContent())) {
            return $this->error('Titre et contenu sont obligatoires.', 422);
        }
        if ('submit' === $action && !$this->access->isOwn($article) && !$this->isGranted('ROLE_ADMIN')) {
            return $this->error('Seul l\'auteur peut soumettre cet article.', 403);
        }

        if (\in_array($action, ['publish', 'schedule'], true) && $article->isPendingApproval()) {
            $article->setPendingApproval(false)->setRequestedStatus(null);
        }

        match ($action) {
            'submit' => $this->articleSaver->submitForReview($article),
            'approve_review' => $this->articleSaver->approveReview($article),
            'reject_review' => $this->articleSaver->rejectReview($article, $reason),
            'publish' => $this->publishNow($article),
            'schedule' => $this->articleSaver->validateAndSchedule($article, $publishAt),
            'reject_correction' => $this->articleSaver->rejectCorrection($article, $reason),
            'unpublish' => $this->articleSaver->unpublish($article),
            'unschedule' => $this->articleSaver->unpublish($article->setPublishedAt(null)),
            'archive' => $this->articleSaver->archive($article),
            'unarchive' => $this->articleSaver->unarchive($article),
            default => throw $this->createNotFoundException(),
        };

        return $this->json(['article' => $this->detail($article)]);
    }

    #[Route('/{id}', name: 'mobile_article_trash', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function trash(int $id): JsonResponse
    {
        $this->requireBrowse();
        $article = $this->find($id);

        if (!$this->access->canTrash($article)) {
            return $this->error('Vous ne pouvez pas mettre cet article à la corbeille.', 403);
        }

        $this->articleSaver->softDelete($article);

        return $this->json(['ok' => true]);
    }

    #[Route('/options', name: 'mobile_article_options', methods: ['GET'], priority: 1)]
    public function options(CategoryRepository $categories): JsonResponse
    {
        $this->requireBrowse();

        $named = static fn (object $item): array => ['id' => $item->getId(), 'name' => $item->getName()];

        return $this->json([
            'categories' => array_map($named, $categories->findBy([], ['name' => 'ASC'])),
            'tags' => array_map($named, $this->entityManager->getRepository(Tag::class)->findBy([], ['name' => 'ASC'])),
        ]);
    }

    private function requireBrowse(): void
    {
        if (!$this->access->canBrowse()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function find(int $id): Article
    {
        // findOneBy applique le filtre de corbeille, contrairement à find().
        return $this->articleRepository->findOneBy(['id' => $id]) ?? throw $this->createNotFoundException();
    }

    private function publishNow(Article $article): void
    {
        // Une date de programmation future ne doit pas survivre à une publication immédiate.
        if ($article->getPublishedAt() > new \DateTimeImmutable()) {
            $article->setPublishedAt(null);
        }
        $this->articleSaver->validateAndPublish($article);
    }

    /**
     * Reporte les champs reçus sur l'article et le valide ; en cas de
     * refus, rien n'est enregistré (aucun flush n'a lieu). Le statut et
     * l'auteur ne se choisissent jamais depuis l'application : le premier
     * suit le circuit, le second est fixé à la création.
     *
     * @param array<string, mixed> $data
     */
    private function apply(Article $article, array $data): ?JsonResponse
    {
        if (\array_key_exists('title', $data)) {
            $article->setTitle(trim((string) $data['title']));
        }

        if (\array_key_exists('content', $data) && (string) $data['content'] !== $article->getContent()) {
            if (!$this->contentPolicy->isSupported($article->getContent())) {
                return $this->refuse('Le texte de cet article contient des éléments que l\'application ne sait pas conserver : modifiez-le sur le site.');
            }
            if (!$this->contentPolicy->isSupported((string) $data['content'])) {
                return $this->refuse('Le texte contient une mise en forme non autorisée.');
            }
            $article->setContent((string) $data['content']);
        }

        if (\array_key_exists('categoryId', $data)) {
            $article->setCategory(null === $data['categoryId'] ? null : $this->entityManager->getRepository(Category::class)->find((int) $data['categoryId']));
        }

        if (\array_key_exists('tagIds', $data)) {
            $tags = $this->entityManager->getRepository(Tag::class)->findBy(['id' => array_map('intval', (array) $data['tagIds'])]);
            foreach ($article->getTags()->toArray() as $tag) {
                if (!\in_array($tag, $tags, true)) {
                    $article->removeTag($tag);
                }
            }
            foreach ($tags as $tag) {
                $article->addTag($tag);
            }
        }

        if (\array_key_exists('coverImage', $data)) {
            $cover = trim((string) $data['coverImage']);
            if (str_starts_with($cover, 'data:') || mb_strlen($cover) > 255) {
                return $this->refuse('Image de couverture invalide.');
            }
            $article->setCoverImage('' === $cover ? null : $cover);
        }

        if ($this->isGranted('articles.publish')) {
            if (\array_key_exists('featured', $data)) {
                $article->setFeatured((bool) $data['featured']);
            }
            if (\array_key_exists('pinned', $data)) {
                $article->setPinned((bool) $data['pinned']);
            }
        }

        if (!$article->getTitle() || !$article->getContent()) {
            return $this->refuse('Titre et contenu sont obligatoires.');
        }

        $violations = $this->validator->validate($article);

        return \count($violations) > 0 ? $this->violations($violations) : null;
    }

    private function refuse(string $message): JsonResponse
    {
        return $this->error($message, 422);
    }

    private function persist(Article $article): void
    {
        $coverChanged = $article->getCoverImage() !== ($this->entityManager->getUnitOfWork()->getOriginalEntityData($article)['coverImage'] ?? null);

        // La signature ne change pas depuis l'application : le contrôle
        // « attribué à un autre auteur » n'a pas lieu d'être ici (il
        // renverrait en brouillon l'article d'un collègue qu'on corrige).
        $wasPublished = $this->articleSaver->applyDefaults($article, enforceAuthorship: false);
        $this->articleSaver->save($article, $wasPublished);

        // Comme au back-office, une couverture téléversée rejoint la médiathèque.
        if ($coverChanged && str_starts_with((string) $article->getCoverImage(), '/uploads/images/')) {
            $media = (new Media())
                ->setType(MediaType::Image)
                ->setArticle($article)
                ->setImageUrl($article->getCoverImage())
                ->setTitle($article->getTitle());
            $this->entityManager->persist($media);
            $this->entityManager->flush();
        }
    }

    /** @param array<string, mixed> $data */
    private function matches(Article $article, array $data): bool
    {
        $current = $this->detail($article);
        $compared = 0;

        foreach (['title', 'content', 'categoryId', 'tagIds', 'coverImage', 'featured', 'pinned'] as $field) {
            if (!\array_key_exists($field, $data)) {
                continue;
            }
            $sent = $data[$field];
            $stored = $current[$field];
            if ('tagIds' === $field) {
                $sent = array_map('intval', (array) $sent);
                sort($sent);
            }
            if ('coverImage' === $field) {
                $sent = (string) $sent;
                $stored = (string) $stored;
            }
            if ($sent !== $stored) {
                return false;
            }
            ++$compared;
        }

        return $compared > 0;
    }

    private function conflict(Article $article): JsonResponse
    {
        return $this->json([
            'error' => 'Cet article a été modifié entre-temps. Rechargez-le avant de continuer.',
            'article' => $this->detail($article),
        ], 409);
    }

    /** Empreinte de ce qui est affiché : change dès que quelqu'un modifie l'article. */
    private function version(Article $article): string
    {
        return sha1(serialize([
            $article->getTitle(),
            $article->getContent(),
            $article->getCoverImage(),
            $article->getStatus()->value,
            $article->getCategory()?->getId(),
            $this->tagIds($article),
            $article->isFeatured(),
            $article->isPinned(),
            $article->isPendingApproval(),
            $article->getPublishedAt()?->format('Y-m-d H:i:s'),
        ]));
    }

    /** @return list<int> */
    private function tagIds(Article $article): array
    {
        $ids = array_values(array_map(static fn (Tag $tag): int => (int) $tag->getId(), $article->getTags()->toArray()));
        sort($ids);

        return $ids;
    }

    /** @return array<string, mixed> */
    private function summary(Article $article): array
    {
        $author = $article->getAuthor();

        return [
            'id' => $article->getId(),
            'title' => $article->getTitle(),
            'status' => $article->getStatus()->value,
            'category' => $article->getCategory()?->getName(),
            'author' => $author ? trim($author->getFirstName().' '.$author->getLastName()) : null,
            'coverImage' => $article->getCoverImage(),
            'updatedAt' => $this->date($article->getUpdatedAt()),
            'publishedAt' => $this->date($article->getPublishedAt()),
            'pendingApproval' => $article->isPendingApproval(),
            'rejectionReason' => $article->getRejectionReason(),
            'own' => $this->access->isOwn($article),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Article $article): array
    {
        return $this->summary($article) + [
            'content' => $article->getContent(),
            'slug' => $article->getSlug(),
            'categoryId' => $article->getCategory()?->getId(),
            'tagIds' => $this->tagIds($article),
            'featured' => $article->isFeatured(),
            'pinned' => $article->isPinned(),
            'version' => $this->version($article),
            'canEdit' => $this->access->canEdit($article),
            'canEditBody' => $this->contentPolicy->isSupported($article->getContent()),
            'canTrash' => $this->access->canTrash($article),
            'actions' => $this->access->actions($article),
        ];
    }
}
