import { Controller } from '@hotwired/stimulus';

/*
 * Auto-rotating hero slider for the homepage (templates/partials/home_sections/_hero_slider.html.twig).
 * data-controller="hero-slider" wraps one data-hero-slider-target="slide" per
 * article and one data-hero-slider-target="dot" per slide, in the same order.
 */
export default class extends Controller {
    static targets = ['slide', 'dot'];
    static values = { interval: { type: Number, default: 6000 } };

    connect() {
        this.index = 0;
        this.show(0);
        this.restartAutoplay();
    }

    disconnect() {
        window.clearInterval(this.timer);
    }

    next() {
        this.show((this.index + 1) % this.slideTargets.length);
        this.restartAutoplay();
    }

    previous() {
        this.show((this.index - 1 + this.slideTargets.length) % this.slideTargets.length);
        this.restartAutoplay();
    }

    goTo(event) {
        const target = event.params.index;
        if (undefined === target) {
            return;
        }
        this.show(target);
        this.restartAutoplay();
    }

    show(index) {
        this.index = index;

        this.slideTargets.forEach((slide, i) => {
            slide.style.display = i === index ? 'block' : 'none';
        });

        this.dotTargets.forEach((dot, i) => {
            dot.style.opacity = i === index ? '1' : '.4';
        });
    }

    restartAutoplay() {
        window.clearInterval(this.timer);

        if (this.slideTargets.length > 1) {
            this.timer = window.setInterval(() => this.next(), this.intervalValue);
        }
    }
}
