import { Controller } from '@hotwired/stimulus';

/*
 * Opens/closes the mobile nav drawer — see the .bb-header-nav /
 * .bb-menu-toggle rules in assets/styles/app.css (the toggle button and
 * drawer behavior only kick in below 760px; above that the nav is just a
 * normal inline row and this controller has nothing to do).
 */
export default class extends Controller {
    static targets = ['nav'];

    toggle() {
        this.navTarget.classList.toggle('bb-nav-open');
    }

    close() {
        this.navTarget.classList.remove('bb-nav-open');
    }
}
