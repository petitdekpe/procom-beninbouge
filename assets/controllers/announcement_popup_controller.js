import { Controller } from '@hotwired/stimulus';

/*
 * Homepage paid pop-up (templates/partials/_announcement_popup.html.twig).
 * Several paying announcements scroll as a slider (scroll-snap + arrows + dots).
 * Opens a couple of seconds after the page loads. An announcement the visitor
 * closed in the last 24 hours is dropped from the slider (remembered in
 * localStorage, per announcement reference, so a new advertiser's slide still
 * shows). When every slide was seen, the pop-up stays closed.
 */
const DELAY_MS = 2500;
const SNOOZE_MS = 24 * 60 * 60 * 1000;

export default class extends Controller {
    static targets = ['track', 'slide', 'nav', 'dots'];

    connect() {
        this.slideTargets.filter((slide) => this.recentlyClosed(slide.dataset.key)).forEach((slide) => slide.remove());

        if (0 === this.slideTargets.length) {
            return;
        }

        this.buildDots();
        this.current = 0;

        this.timer = window.setTimeout(() => {
            // Ne pas empiler le pop-up sur une autre fenêtre déjà ouverte (lecteur vidéo, menu…).
            if (!document.querySelector('dialog[open]')) {
                this.element.showModal();
                this.refresh();
            }
        }, DELAY_MS);
    }

    disconnect() {
        window.clearTimeout(this.timer);
    }

    close() {
        this.element.close();
    }

    closeOnBackdrop(event) {
        if (event.target === this.element) {
            this.element.close();
        }
    }

    prev() {
        this.goTo(this.current - 1);
    }

    next() {
        this.goTo(this.current + 1);
    }

    goTo(index) {
        const count = this.slideTargets.length;
        const target = (index + count) % count;

        this.trackTarget.scrollTo({ left: target * this.trackTarget.clientWidth, behavior: 'smooth' });
    }

    onScroll() {
        const width = this.trackTarget.clientWidth;
        if (!width) {
            return;
        }

        const index = Math.round(this.trackTarget.scrollLeft / width);
        if (index !== this.current) {
            this.current = index;
            this.refresh();
        }
    }

    buildDots() {
        if (!this.hasDotsTarget) {
            return;
        }

        this.dotsTarget.replaceChildren(
            ...this.slideTargets.map((_, index) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'bb-ann-popup-dot';
                dot.setAttribute('aria-label', `Annonce ${index + 1}`);
                dot.addEventListener('click', () => this.goTo(index));

                return dot;
            }),
        );

        // Un seul slide restant après filtrage : pas de navigation.
        if (this.hasNavTarget) {
            this.navTarget.hidden = this.slideTargets.length < 2;
        }
    }

    refresh() {
        if (!this.hasDotsTarget) {
            return;
        }

        [...this.dotsTarget.children].forEach((dot, index) => {
            dot.classList.toggle('is-active', index === this.current);
            dot.setAttribute('aria-current', index === this.current ? 'true' : 'false');
        });
    }

    // Déclenché par l'évènement natif « close » (bouton, Échap ou clic hors de la fenêtre).
    remember() {
        const now = String(Date.now());

        this.slideTargets.forEach((slide) => {
            try {
                window.localStorage.setItem(this.storageKey(slide.dataset.key), now);
            } catch {
                // Stockage indisponible (navigation privée…) : le pop-up réapparaîtra, sans gravité.
            }
        });
    }

    recentlyClosed(key) {
        try {
            const closedAt = Number(window.localStorage.getItem(this.storageKey(key)));

            return closedAt > 0 && Date.now() - closedAt < SNOOZE_MS;
        } catch {
            return false;
        }
    }

    storageKey(key) {
        return `bb-announcement-popup:${key}`;
    }
}
