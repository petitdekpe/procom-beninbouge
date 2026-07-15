import { Controller } from '@hotwired/stimulus';
import Quill from 'quill';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        uploadUrl: { type: String, default: '/admin/editor/upload-image' },
        csrfToken: { type: String, default: 'csrf-token' },
        size: { type: String, default: 'normal' },
    };

    connect() {
        const textarea = this.element;

        // The native `required` attribute can't show its validation bubble on
        // a hidden field (browsers refuse to focus it), which would silently
        // block the wizard's step navigation with no visible feedback. The
        // server-side NotBlank constraint on Article::$content remains the
        // real safety net.
        textarea.removeAttribute('required');
        textarea.style.display = 'none';

        const container = document.createElement('div');
        container.className = 'bo-richtext';
        if ('tall' === this.sizeValue) {
            container.classList.add('bo-richtext-tall');
        }
        textarea.insertAdjacentElement('afterend', container);

        const quill = new Quill(container, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'link'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['image', 'clean'],
                    ],
                    handlers: {
                        image: () => this.pickImage(quill),
                    },
                },
            },
        });

        if (textarea.value) {
            quill.clipboard.dangerouslyPasteHTML(textarea.value);
        }

        quill.on('text-change', () => {
            textarea.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    pickImage(quill) {
        const range = quill.getSelection(true);
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/webp,image/gif';

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) {
                return;
            }

            const data = new FormData();
            data.append('image', file);

            fetch(this.uploadUrlValue, {
                method: 'POST',
                body: data,
                headers: { 'X-CSRF-TOKEN': this.csrfTokenValue },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('upload failed');
                    }

                    return response.json();
                })
                .then((json) => {
                    quill.insertEmbed(range.index, 'image', json.url, 'user');
                    quill.setSelection(range.index + 1, 0, 'user');
                })
                .catch(() => {
                    window.alert("L'image n'a pas pu être téléversée.");
                });
        });

        input.click();
    }
}
