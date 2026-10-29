import { Controller } from '@hotwired/stimulus';

/*
 * Full-screen crossfading hero for the homepage
 * (templates/partials/home_sections/_hero_slider.html.twig), modelled on the
 * beninroots.bj "accueil" carousel: rotates every 10s until the reader picks
 * a slide (dot or swipe), after which autoplay stays off. One
 * data-hero-slider-target="slide" per article and one "dot" per slide, in
 * the same order. Styling lives in .bb-hero* in assets/styles/app.css.
 */
const SWIPE_THRESHOLD = 50;

export default class extends Controller {
    static targets = ['slide', 'dot'];
    static values = { interval: { type: Number, default: 10000 } };

    connect() {
        this.index = 0;
        this.show(0);
        this.startAutoplay();
    }

    disconnect() {
        this.stopAutoplay();
    }

    next() {
        this.show((this.index + 1) % this.slideTargets.length);
    }

    previous() {
        this.show((this.index - 1 + this.slideTargets.length) % this.slideTargets.length);
    }

    goTo(event) {
        const target = event.params.index;
        if (undefined === target) {
            return;
        }
        this.stopAutoplay();
        this.show(target);
    }

    touchStart(event) {
        this.touchStartX = this.touchCurrentX = event.touches[0].clientX;
    }

    touchMove(event) {
        this.touchCurrentX = event.touches[0].clientX;
    }

    touchEnd() {
        const delta = this.touchStartX - this.touchCurrentX;
        if (Math.abs(delta) < SWIPE_THRESHOLD) {
            return;
        }
        this.stopAutoplay();
        delta > 0 ? this.next() : this.previous();
    }

    show(index) {
        this.index = index;

        this.slideTargets.forEach((slide, i) => {
            const active = i === index;
            slide.classList.toggle('is-active', active);
            slide.toggleAttribute('inert', !active);
            if (active) {
                slide.removeAttribute('aria-hidden');
            } else {
                slide.setAttribute('aria-hidden', 'true');
            }
        });

        this.dotTargets.forEach((dot, i) => {
            dot.classList.toggle('is-active', i === index);
            if (i === index) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
    }

    startAutoplay() {
        if (this.slideTargets.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.timer = window.setInterval(() => this.next(), this.intervalValue);
        }
    }

    stopAutoplay() {
        window.clearInterval(this.timer);
    }
}
