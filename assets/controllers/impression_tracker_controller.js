import { Controller } from '@hotwired/stimulus';

/*
 * Fires one impression per visible article card within this controller's
 * element when a listing page loads (homepage sections, rubrique/mot-clé/
 * dossier pages) — paired against ArticleView to approximate a
 * click-through rate. data-controller="impression-tracker" wraps a grid
 * of cards, each card tagged data-impression-tracker-target="card"
 * data-article-id="{{ article.id }}"; data-impression-tracker-context-value
 * sets the listing's label (e.g. "home_latest_news").
 */
export default class extends Controller {
    static values = { context: String };
    static targets = ['card'];

    connect() {
        this.cardTargets.forEach((card) => {
            const articleId = card.dataset.articleId;
            if (!articleId) {
                return;
            }

            fetch('/api/article_impressions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/ld+json' },
                body: JSON.stringify({
                    article: `/api/articles/${articleId}`,
                    context: this.contextValue || null,
                }),
                keepalive: true,
            }).catch(() => {});
        });
    }
}
