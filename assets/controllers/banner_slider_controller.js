import { Controller } from '@hotwired/stimulus';

/*
 * Slider d'images de l'accueil (templates/partials/home_sections/_banner.html.twig).
 * Les diapositives défilent horizontalement avec scroll-snap : le balayage
 * tactile, la molette et le clavier (le conteneur est focusable) marchent
 * sans JavaScript. Ce contrôleur ajoute les flèches, les points et le
 * défilement automatique, suspendu au survol, au focus clavier, pendant un
 * toucher, quand l'onglet est caché et si le visiteur préfère réduire les
 * animations.
 */
export default class extends Controller {
    static targets = ['track', 'slide', 'dot'];
    static values = { interval: { type: Number, default: 5500 } };

    connect() {
        this.index = 0;
        this.paused = false;
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.onScroll = () => {
            window.clearTimeout(this.scrollTimer);
            this.scrollTimer = window.setTimeout(() => this.syncFromScroll(), 80);
        };
        this.trackTarget.addEventListener('scroll', this.onScroll, { passive: true });

        this.pause = () => { this.paused = true; };
        this.resume = () => { this.paused = false; };
        for (const event of ['mouseenter', 'focusin', 'touchstart']) {
            this.element.addEventListener(event, this.pause, { passive: true });
        }
        for (const event of ['mouseleave', 'focusout', 'touchend']) {
            this.element.addEventListener(event, this.resume, { passive: true });
        }

        this.updateDots();
        this.start();
    }

    disconnect() {
        this.stop();
        window.clearTimeout(this.scrollTimer);
        this.trackTarget.removeEventListener('scroll', this.onScroll);
        for (const event of ['mouseenter', 'focusin', 'touchstart']) {
            this.element.removeEventListener(event, this.pause);
        }
        for (const event of ['mouseleave', 'focusout', 'touchend']) {
            this.element.removeEventListener(event, this.resume);
        }
    }

    next() {
        this.goToIndex(this.index + 1);
    }

    prev() {
        this.goToIndex(this.index - 1);
    }

    goTo(event) {
        this.goToIndex(event.params.index);
    }

    goToIndex(target) {
        const count = this.slideTargets.length;
        if (count < 2) {
            return;
        }

        this.index = (target + count) % count;
        this.trackTarget.scrollTo({
            left: this.index * this.trackTarget.clientWidth,
            behavior: this.reducedMotion ? 'auto' : 'smooth',
        });
        this.updateDots();
    }

    syncFromScroll() {
        const width = this.trackTarget.clientWidth;
        if (!width) {
            return;
        }

        this.index = Math.round(this.trackTarget.scrollLeft / width);
        this.updateDots();
    }

    updateDots() {
        this.dotTargets.forEach((dot, i) => {
            const active = i === this.index;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-current', active ? 'true' : 'false');
        });
    }

    start() {
        if (this.reducedMotion || this.slideTargets.length < 2) {
            return;
        }

        this.timer = window.setInterval(() => {
            if (!this.paused && !document.hidden) {
                this.next();
            }
        }, this.intervalValue);
    }

    stop() {
        window.clearInterval(this.timer);
    }
}
