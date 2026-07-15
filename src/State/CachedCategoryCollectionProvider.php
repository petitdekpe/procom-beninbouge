<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Repository\CategoryRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Caches the Category listing in Redis for a short time. Only the
 * matching entity IDs (plus pagination metadata) are cached - the
 * entities themselves are always re-fetched through Doctrine on a cache
 * hit, so there is no risk of serving stale/detached entity objects.
 *
 * @implements ProviderInterface<\App\Entity\Category>
 */
final class CachedCategoryCollectionProvider implements ProviderInterface
{
    private const TTL = 60;

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $decorated,
        #[Autowire(service: 'cache.listings')]
        private CacheItemPoolInterface $listingsCache,
        private CategoryRepository $categoryRepository,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $cacheKey = 'category_collection_'.hash('xxh3', (string) $this->requestStack->getCurrentRequest()?->getQueryString());

        $item = $this->listingsCache->getItem($cacheKey);

        if ($item->isHit()) {
            [$ids, $currentPage, $itemsPerPage, $totalItems] = $item->get();

            $categories = $ids ? $this->categoryRepository->findBy(['id' => $ids]) : [];
            $byId = [];
            foreach ($categories as $category) {
                $byId[$category->getId()] = $category;
            }
            $ordered = array_values(array_filter(array_map(static fn (int $id) => $byId[$id] ?? null, $ids)));

            return new TraversablePaginator(new \ArrayIterator($ordered), $currentPage, $itemsPerPage, $totalItems);
        }

        $result = $this->decorated->provide($operation, $uriVariables, $context);

        if (!$result instanceof PaginatorInterface) {
            return $result;
        }

        $ids = [];
        foreach ($result as $category) {
            $ids[] = $category->getId();
        }

        $item->set([$ids, $result->getCurrentPage(), $result->getItemsPerPage(), $result->getTotalItems()]);
        $item->expiresAfter(self::TTL);
        $this->listingsCache->save($item);

        return $result;
    }
}
