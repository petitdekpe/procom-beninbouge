import { Controller } from '@hotwired/stimulus';

/*
 * Mobile nav drawer (templates/partials/_header.html.twig), modelled on the
 * beninroots.bj side sheet: slides in from the right over a dark overlay,
 * closes on overlay click, Escape, the close button or any link. Below 768px
 * only — see the .bb-drawer* rules in assets/styles/app.css.
 */
export default class extends Controller {
    static targets = ['drawer', 'overlay', 'button'];

    disconnect() {
        document.documentElement.style.overflow = '';
    }

    open() {
        window.clearTimeout(this.hideTimer);
        this.drawerTarget.hidden = false;
        this.overlayTarget.hidden = false;
        // Force a reflow so the transition runs from the closed state.
        this.drawerTarget.offsetWidth;
        this.element.classList.add('bb-drawer-open');
        this.buttonTarget.setAttribute('aria-expanded', 'true');
        document.documentElement.style.overflow = 'hidden';
        this.drawerTarget.querySelector('a, button')?.focus();
    }

    close() {
        if (!this.element.classList.contains('bb-drawer-open')) {
            return;
        }

        this.element.classList.remove('bb-drawer-open');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        document.documentElement.style.overflow = '';
        if (this.drawerTarget.contains(document.activeElement)) {
            this.buttonTarget.focus();
        }
        this.hideTimer = window.setTimeout(() => {
            this.drawerTarget.hidden = true;
            this.overlayTarget.hidden = true;
        }, 300);
    }
}
