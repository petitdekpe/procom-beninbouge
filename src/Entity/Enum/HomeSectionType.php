<?php

namespace App\Entity\Enum;

enum HomeSectionType: string
{
    case HeroSlider = 'hero_slider';
    case Banner = 'banner';
    case LatestNews = 'latest_news';
    case PopularArticles = 'popular_articles';
    case Videos = 'videos';
    case Recap = 'recap';
    case Podcasts = 'podcasts';
    case CategoryHighlight = 'category_highlight';
    case Contact = 'contact';
    case Announcements = 'announcements';
}
