import { Controller } from '@hotwired/stimulus';
import { getVisitorId } from '../visitor.js';

/*
 * Fires once per click on a share button — doesn't block navigation,
 * since share links open in a new tab (target="_blank") and the current
 * page never unloads. data-controller="share-tracker" goes on the same
 * wrapper as article-tracker; each share link gets
 * data-action="click->share-tracker#track" and
 * data-share-tracker-channel-param="facebook" (etc).
 */
export default class extends Controller {
    static values = { articleId: Number };

    track(event) {
        const channel = event.params.channel;
        if (!channel) {
            return;
        }

        fetch('/api/article_shares', {
            method: 'POST',
            headers: { 'Content-Type': 'application/ld+json' },
            body: JSON.stringify({
                article: `/api/articles/${this.articleIdValue}`,
                channel,
                visitorId: getVisitorId(),
            }),
            keepalive: true,
        }).catch(() => {});
    }
}
