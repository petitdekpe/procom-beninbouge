import { Controller } from '@hotwired/stimulus';

/*
 * Live price estimate on the « Publier une annonce » form
 * (templates/announcement/submit.html.twig): (page rate + chosen options) ×
 * days, with the admin rates passed in data-announcement-price-rates-value.
 * Display only: the amount actually charged is recomputed server-side by
 * App\Service\AnnouncementPricing.
 */
export default class extends Controller {
    static targets = ['days', 'homeCard', 'popup', 'daysLabel', 'daily', 'total'];
    static values = { rates: Object };

    connect() {
        this.format = new Intl.NumberFormat('fr-FR');
        this.update();
    }

    update() {
        const { page, homeCard, popup, minDays, maxDays } = this.ratesValue;
        const typed = parseInt(this.daysTarget.value, 10);
        const days = Number.isNaN(typed) ? minDays : Math.min(Math.max(typed, minDays), maxDays);

        const daily = page
            + (this.homeCardTarget.checked ? homeCard : 0)
            + (this.popupTarget.checked ? popup : 0);

        this.daysLabelTarget.textContent = `${days} jour${days > 1 ? 's' : ''}`;
        this.dailyTarget.textContent = `${this.format.format(daily)} FCFA`;
        this.totalTarget.textContent = `${this.format.format(daily * days)} FCFA`;
    }
}
