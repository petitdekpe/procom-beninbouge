import { Controller } from '@hotwired/stimulus';

/*
 * Couvertures d'articles sans coupure. Les vignettes sont en paysage
 * (16/10, 4/3) et recadrent en object-fit: cover : une image portrait
 * comme 1024 × 1200 y perdrait la moitié de sa hauteur. Quand l'image est
 * nettement plus haute que son cadre, son parent reçoit .bb-fit-contain :
 * l'image s'affiche entière, sur un fond flou tiré d'elle-même
 * (styles dans assets/styles/base.css). Le calcul est refait si le cadre
 * change de taille (passage mobile / bureau).
 *
 * Usage : <img src="…" data-controller="cover-fit"> dans un cadre qui a
 * overflow: hidden et un position autre que static.
 */

// En dessous de 80 % du ratio du cadre, le recadrage devient trop visible.
const TOLERANCE = 0.8;

function fit(img) {
    const frame = img.parentElement;
    if (!frame || !img.naturalWidth || !img.clientWidth || !img.clientHeight) {
        return;
    }

    const imageRatio = img.naturalWidth / img.naturalHeight;
    const frameRatio = img.clientWidth / img.clientHeight;
    const contain = imageRatio < frameRatio * TOLERANCE;

    frame.classList.toggle('bb-fit-contain', contain);
    if (contain) {
        const src = (img.currentSrc || img.src).replace(/["\\]/g, '\\$&');
        frame.style.setProperty('--bb-fit-src', `url("${src}")`);
    }
}

const observer = typeof ResizeObserver === 'undefined'
    ? null
    : new ResizeObserver((entries) => entries.forEach((entry) => fit(entry.target)));

export default class extends Controller {
    connect() {
        this.onLoad = () => fit(this.element);
        this.element.addEventListener('load', this.onLoad);
        if (this.element.complete) {
            this.onLoad();
        }
        observer?.observe(this.element);
    }

    disconnect() {
        this.element.removeEventListener('load', this.onLoad);
        observer?.unobserve(this.element);
    }
}
