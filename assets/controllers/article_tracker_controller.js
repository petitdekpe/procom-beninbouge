import { Controller } from '@hotwired/stimulus';
import { getVisitorId } from '../visitor.js';

/*
 * Records one real view of an article, including actual time-on-page,
 * fired the moment the reader leaves — via visibilitychange (tab hidden)
 * or pagehide (navigation/close), whichever comes first. More reliable
 * than beforeunload/unload, especially on mobile. Fires at most once per
 * page load. data-controller="article-tracker" goes on the article page
 * wrapper; data-article-tracker-article-id-value carries the article's id.
 */
export default class extends Controller {
    static values = { articleId: Number };

    connect() {
        this.startedAt = Date.now();
        this.sent = false;

        this.handleVisibilityChange = () => {
            if ('hidden' === document.visibilityState) {
                this.sendView();
            }
        };
        this.handlePageHide = () => this.sendView();

        document.addEventListener('visibilitychange', this.handleVisibilityChange);
        window.addEventListener('pagehide', this.handlePageHide);
    }

    disconnect() {
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        window.removeEventListener('pagehide', this.handlePageHide);
    }

    sendView() {
        if (this.sent) {
            return;
        }
        this.sent = true;

        const durationSeconds = Math.min(Math.round((Date.now() - this.startedAt) / 1000), 3600);
        const payload = {
            article: `/api/articles/${this.articleIdValue}`,
            source: this.detectSource(),
            visitorId: getVisitorId(),
            durationSeconds,
        };

        const blob = new Blob([JSON.stringify(payload)], { type: 'application/ld+json' });
        navigator.sendBeacon('/api/article_views', blob);
    }

    detectSource() {
        const referrer = document.referrer;
        if (!referrer) {
            return 'direct';
        }

        try {
            const host = new URL(referrer).hostname.replace(/^www\./, '');
            if (host === window.location.hostname) {
                return 'internal';
            }
            if (/facebook\.com/.test(host)) return 'facebook';
            if (/(twitter\.com|x\.com)/.test(host)) return 'twitter';
            if (/whatsapp\.com/.test(host)) return 'whatsapp';
            if (/linkedin\.com/.test(host)) return 'linkedin';
            if (/google\./.test(host)) return 'google';

            return host;
        } catch {
            return 'direct';
        }
    }
}
