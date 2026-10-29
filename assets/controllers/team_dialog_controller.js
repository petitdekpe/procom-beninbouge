import { Controller } from '@hotwired/stimulus';

/*
 * Team member bio windows on the À propos page (templates/about/index.html.twig),
 * like the beninroots.bj team modal: clicking a member's card opens their
 * <dialog>; Escape (native), the close button or a click on the backdrop
 * closes it.
 */
export default class extends Controller {
    open(event) {
        const dialog = document.getElementById(event.params.id);
        dialog?.showModal();
    }

    close(event) {
        event.currentTarget.closest('dialog')?.close();
    }

    closeOnBackdrop(event) {
        // A click on the ::backdrop targets the <dialog> element itself.
        if (event.target === event.currentTarget) {
            event.currentTarget.close();
        }
    }
}
