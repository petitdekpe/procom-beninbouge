import { Controller } from '@hotwired/stimulus';

/*
 * Formulaire admin des récaps : génère l'image de couverture à partir de la
 * vidéo, dans le navigateur (aucun logiciel serveur requis).
 *
 *  - au choix d'un fichier vidéo : une image est capturée et placée dans le
 *    champ « téléversez une image » (sauf si une image a été choisie à la
 *    main), puis envoyée avec le formulaire ;
 *  - bouton « Générer la couverture depuis la vidéo » : fait de même pour
 *    le fichier choisi ou, à défaut, pour la vidéo déjà enregistrée (cas des
 *    récaps existants sans couverture).
 *
 * Les liens YouTube n'ont pas besoin de ça : leur couverture est détectée à
 * l'enregistrement (App\Service\RecapCoverResolver).
 */
const MAX_WIDTH = 720;
const TIMEOUT_MS = 20000;
const VIDEO_FILE = /\.(mp4|webm|m4v|mov)(\?|#|$)/i;

export default class extends Controller {
    static targets = ['video', 'cover', 'status', 'preview'];
    static values = { existingUrl: String };

    connect() {
        // Vrai tant que l'image du champ a été produite ici, pas choisie à la main.
        this.generated = false;
    }

    videoChanged() {
        if (!this.videoTarget.files.length) {
            return;
        }

        // Une image choisie à la main reste prioritaire.
        if (this.coverTarget.files.length > 0 && !this.generated) {
            return;
        }

        this.generate();
    }

    coverChanged() {
        // Appelé uniquement par un choix de l'utilisateur (assigner .files ne déclenche pas « change »).
        this.generated = false;

        const file = this.coverTarget.files[0];
        if (file) {
            this.showPreview(file);
            this.say('');
        }
    }

    async generate() {
        const file = this.videoTarget.files[0];
        const source = file ? URL.createObjectURL(file) : this.existingUrlValue;

        if (!source) {
            this.say('Choisissez d\'abord un fichier vidéo (MP4 ou WebM).', true);

            return;
        }

        if (!file && !VIDEO_FILE.test(source)) {
            this.say('Pour un lien YouTube, la couverture est détectée automatiquement à l\'enregistrement.');

            return;
        }

        this.say('Génération de la couverture…');

        try {
            const blob = await this.captureFrame(source);
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], 'couverture.jpg', { type: 'image/jpeg' }));

            this.coverTarget.files = transfer.files;
            this.generated = true;
            this.showPreview(blob);
            this.say('Couverture générée depuis la vidéo : elle sera enregistrée avec le récap. Vous pouvez la remplacer en choisissant une autre image.');
        } catch (error) {
            this.say('Ce navigateur n\'a pas pu lire la vidéo : téléversez une image de couverture à la place.', true);
        } finally {
            if (file) {
                URL.revokeObjectURL(source);
            }
        }
    }

    captureFrame(source) {
        return new Promise((resolve, reject) => {
            const video = document.createElement('video');
            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';

            const cleanup = () => {
                clearTimeout(timer);
                video.removeAttribute('src');
                video.load();
            };
            const fail = (reason) => {
                cleanup();
                reject(new Error(reason));
            };
            const timer = setTimeout(() => fail('timeout'), TIMEOUT_MS);

            video.addEventListener('error', () => fail('video'), { once: true });

            video.addEventListener('loadedmetadata', () => {
                // Une seconde dans la vidéo (ou le milieu d'un clip très court) :
                // la toute première image est souvent noire.
                const duration = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 0.2;
                video.currentTime = Math.min(1, Math.max(0.05, duration / 2));
            }, { once: true });

            video.addEventListener('seeked', () => {
                const width = video.videoWidth;
                const height = video.videoHeight;
                if (!width || !height) {
                    fail('size');

                    return;
                }

                const scale = Math.min(1, MAX_WIDTH / width);
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(width * scale);
                canvas.height = Math.round(height * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

                try {
                    canvas.toBlob((blob) => {
                        cleanup();
                        blob ? resolve(blob) : reject(new Error('blob'));
                    }, 'image/jpeg', 0.85);
                } catch (error) {
                    fail('canvas');
                }
            }, { once: true });

            video.src = source;
        });
    }

    showPreview(blobOrFile) {
        if (!this.hasPreviewTarget) {
            return;
        }

        if (this.previewUrl) {
            URL.revokeObjectURL(this.previewUrl);
        }

        this.previewUrl = URL.createObjectURL(blobOrFile);
        this.previewTarget.src = this.previewUrl;
        this.previewTarget.hidden = false;
    }

    say(message, isError = false) {
        if (!this.hasStatusTarget) {
            return;
        }

        this.statusTarget.textContent = message;
        this.statusTarget.style.color = isError ? 'var(--bo-danger, #b42318)' : '';
    }

    disconnect() {
        if (this.previewUrl) {
            URL.revokeObjectURL(this.previewUrl);
        }
    }
}
