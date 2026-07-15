<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AnalyticsOverview;
use App\Repository\ArticleViewRepository;

/**
 * @implements ProviderInterface<AnalyticsOverview>
 */
final class AnalyticsOverviewProvider implements ProviderInterface
{
    public function __construct(private ArticleViewRepository $articleViewRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AnalyticsOverview
    {
        $overview = new AnalyticsOverview();
        $overview->totalViews = $this->articleViewRepository->countTotal();
        $overview->viewsBySource = $this->articleViewRepository->countBySource();
        $overview->topArticles = $this->articleViewRepository->topArticles();

        return $overview;
    }
}
