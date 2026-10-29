<?php

namespace App\Entity\Enum;

enum HomeSectionType: string
{
    case HeroSlider = 'hero_slider';
    case LatestNews = 'latest_news';
    case PopularArticles = 'popular_articles';
    case Videos = 'videos';
    case Podcasts = 'podcasts';
    case CategoryHighlight = 'category_highlight';
    case Contact = 'contact';
    case Announcements = 'announcements';
}
