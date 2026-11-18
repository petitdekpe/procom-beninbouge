import { Controller } from '@hotwired/stimulus';

/*
 * Bouton clair / sombre du header (templates/partials/_header.html.twig).
 * Par défaut le site suit le réglage de l'appareil ; un clic fixe le thème
 * opposé à celui affiché, le pose en data-theme sur <html> (lu par
 * assets/styles/tokens.css) et le mémorise dans localStorage. Le script en
 * tête de templates/base.html.twig le réapplique avant le rendu des pages
 * suivantes, sans flash.
 */
const STORAGE_KEY = 'bb-theme';

export default class extends Controller {
    connect() {
        this.media = window.matchMedia('(prefers-color-scheme: dark)');
        this.onSystemChange = () => this.render();
        this.media.addEventListener?.('change', this.onSystemChange);
        this.render();
    }

    disconnect() {
        this.media?.removeEventListener?.('change', this.onSystemChange);
    }

    toggle() {
        const next = this.current() === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', next);
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (e) {
            // Navigation privée ou stockage bloqué : le choix vaut pour la page.
        }
        this.render();
    }

    current() {
        const chosen = document.documentElement.getAttribute('data-theme');
        if (chosen === 'dark' || chosen === 'light') {
            return chosen;
        }

        return this.media.matches ? 'dark' : 'light';
    }

    render() {
        // Le libellé annonce l'action (pas d'aria-pressed : il contredirait un libellé qui change).
        const isDark = this.current() === 'dark';

        this.element.setAttribute('aria-label', isDark ? 'Activer le thème clair' : 'Activer le thème sombre');
        this.element.dataset.themeCurrent = isDark ? 'dark' : 'light';
    }
}
