import { Controller } from '@hotwired/stimulus';

/*
 * Homepage video library (templates/partials/home_sections/_videos.html.twig),
 * modelled on the beninroots.bj "Vidéothèque": clicking a playlist item loads
 * it into the main player, updates the title/description underneath, marks
 * the item active and scrolls the section back into view.
 */
export default class extends Controller {
    static targets = ['frame', 'title', 'description', 'item'];

    select(event) {
        const { url, title, description } = event.params;
        const item = event.currentTarget;

        if (!url || item.classList.contains('is-active')) {
            return;
        }

        this.frameTarget.src = `${url}?autoplay=1&rel=0&modestbranding=1`;
        this.frameTarget.title = title;
        this.titleTarget.textContent = title;
        this.descriptionTarget.textContent = description ?? '';

        this.itemTargets.forEach((el) => {
            const active = el === item;
            el.classList.toggle('is-active', active);
            if (active) {
                el.setAttribute('aria-current', 'true');
            } else {
                el.removeAttribute('aria-current');
            }
        });

        this.element.closest('section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
