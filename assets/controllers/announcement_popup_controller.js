import { Controller } from '@hotwired/stimulus';

/*
 * Homepage paid pop-up (templates/partials/_announcement_popup.html.twig).
 * Opens a couple of seconds after the page loads, unless the visitor closed
 * this same announcement in the last 24 hours (remembered in localStorage,
 * per announcement reference, so a new advertiser's pop-up still shows).
 */
const DELAY_MS = 2500;
const SNOOZE_MS = 24 * 60 * 60 * 1000;

export default class extends Controller {
    static values = { key: String };

    connect() {
        if (this.recentlyClosed()) {
            return;
        }

        this.timer = window.setTimeout(() => {
            // Ne pas empiler le pop-up sur une autre fenêtre déjà ouverte (lecteur vidéo, menu…).
            if (!document.querySelector('dialog[open]')) {
                this.element.showModal();
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

    // Déclenché par l'évènement natif « close » (bouton, Échap ou clic hors de la fenêtre).
    remember() {
        try {
            window.localStorage.setItem(this.storageKey(), String(Date.now()));
        } catch {
            // Stockage indisponible (navigation privée…) : le pop-up réapparaîtra, sans gravité.
        }
    }

    recentlyClosed() {
        try {
            const closedAt = Number(window.localStorage.getItem(this.storageKey()));

            return closedAt > 0 && Date.now() - closedAt < SNOOZE_MS;
        } catch {
            return false;
        }
    }

    storageKey() {
        return `bb-announcement-popup:${this.keyValue}`;
    }
}
