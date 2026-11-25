import { Controller } from '@hotwired/stimulus';
import { getVisitorId } from '../visitor.js';

/*
 * Mesure de la régie : enregistre l'affichage d'une publicité ou d'une
 * annonce, puis ses clics (App\Controller\AdTrackingController).
 *
 * Un affichage est compté une fois par chargement de page, quand au moins
 * la moitié de l'élément reste visible pendant 800 ms : un contenu hors
 * écran, derrière le pop-up fermé ou masqué par le carrousel n'est pas
 * compté. Un clic est compté quand le visiteur clique un lien de l'élément
 * (clickSelector, « a[href] » par défaut).
 *
 *   data-controller="ad-tracker"
 *   data-ad-tracker-target-value="banner|announcement"
 *   data-ad-tracker-id-value="12"
 *   data-ad-tracker-placement-value="home_slider|home_card|popup|list|detail"
 *   data-ad-tracker-click-selector-value="a[data-ad-click]"   (optionnel)
 */
const DWELL_MS = 800;

export default class extends Controller {
    static values = {
        target: String,
        id: Number,
        placement: String,
        clickSelector: { type: String, default: 'a[href]' },
    };

    connect() {
        this.impressionSent = false;

        this.onClick = (event) => {
            if (event.target instanceof Element && event.target.closest(this.clickSelectorValue)) {
                this.send('click');
            }
        };
        this.element.addEventListener('click', this.onClick);

        if (!('IntersectionObserver' in window)) {
            this.send('impression');
            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                window.clearTimeout(this.timer);
                if (entry.isIntersecting && !this.impressionSent) {
                    this.timer = window.setTimeout(() => this.send('impression'), DWELL_MS);
                }
            }
        }, { threshold: 0.5 });
        this.observer.observe(this.element);
    }

    disconnect() {
        this.element.removeEventListener('click', this.onClick);
        window.clearTimeout(this.timer);
        this.observer?.disconnect();
    }

    send(kind) {
        if ('impression' === kind) {
            if (this.impressionSent) {
                return;
            }
            this.impressionSent = true;
            this.observer?.disconnect();
        }

        const payload = JSON.stringify({
            kind,
            target: this.targetValue,
            id: this.idValue,
            placement: this.placementValue,
            visitorId: getVisitorId(),
        });

        // sendBeacon survit à la navigation déclenchée par le clic ; repli sur fetch.
        const blob = new Blob([payload], { type: 'application/json' });
        if (!(navigator.sendBeacon && navigator.sendBeacon('/regie/evenement', blob))) {
            fetch('/regie/evenement', { method: 'POST', body: payload, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(() => {});
        }
    }
}
