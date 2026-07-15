import { Controller } from '@hotwired/stimulus';

/*
 * Plays a YouTube video inline in an overlay instead of navigating away.
 * data-controller="video-modal" goes on a page wrapper containing both the
 * video links (data-action="video-modal#open" + data-video-modal-url-param)
 * and the overlay markup (see templates/partials/_video_modal.html.twig).
 */
export default class extends Controller {
    static targets = ['overlay', 'iframe'];

    open(event) {
        const url = event.params.url;

        if (!url) {
            return;
        }

        event.preventDefault();

        this.iframeTarget.src = url + (url.includes('?') ? '&' : '?') + 'autoplay=1';
        this.overlayTarget.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.overlayTarget.style.display = 'none';
        this.iframeTarget.src = '';
        document.body.style.overflow = '';
    }

    closeOnBackdrop(event) {
        if (event.target === this.overlayTarget) {
            this.close();
        }
    }

    closeOnEscape(event) {
        if ('Escape' === event.key && 'none' !== this.overlayTarget.style.display) {
            this.close();
        }
    }
}
