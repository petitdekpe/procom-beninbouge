<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\AnalyticsOverviewProvider;

/**
 * Vue agrégée des analytics : vues totales, répartition par source et
 * articles les plus performants. Réservée aux éditeurs/admins.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/analytics/overview',
            security: "is_granted('ROLE_EDITOR')",
            provider: AnalyticsOverviewProvider::class,
        ),
    ],
)]
class AnalyticsOverview
{
    #[ApiProperty(identifier: true)]
    public string $id = 'overview';

    public int $totalViews = 0;

    /**
     * @var list<array{source: string|null, count: int}>
     */
    public array $viewsBySource = [];

    /**
     * @var list<array{id: int, title: string, slug: string, viewsCount: int}>
     */
    public array $topArticles = [];
}
