<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Category;
use App\Entity\Enum\ActivityAction;
use App\Service\ActivityLogger;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Category, void>
 */
final class CategoryDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        #[Autowire(service: 'cache.listings')]
        private CacheItemPoolInterface $listingsCache,
        private ActivityLogger $activityLogger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $data instanceof Category ? $data->getId() : null;

        $result = $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        $this->listingsCache->clear();

        if (null !== $id) {
            $this->activityLogger->log(ActivityAction::Deletion, 'Category', $id);
        }

        return $result;
    }
}
