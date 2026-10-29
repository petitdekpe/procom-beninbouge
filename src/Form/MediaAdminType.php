<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Enum\MediaLicense;
use App\Entity\Enum\MediaType;
use App\Entity\Gallery;
use App\Entity\Media;
use App\Entity\Tag;
use App\Service\DocumentUploader;
use App\Service\ImageUploader;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class MediaAdminType extends AbstractType
{
    public function __construct(
        private ImageUploader $imageUploader,
        private DocumentUploader $documentUploader,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => array_combine(
                    array_map(fn (MediaType $t) => $t->name, MediaType::cases()),
                    MediaType::cases(),
                ),
                'choice_value' => fn (?MediaType $type) => $type?->value,
            ])
            ->add('title', TextType::class, ['label' => 'Titre', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('author', EntityType::class, [
                'label' => 'Auteur',
                'class' => Author::class,
                'choice_label' => fn (Author $author) => trim($author->getFirstName().' '.$author->getLastName()),
                'required' => false,
                'placeholder' => 'Non renseigné',
            ])
            ->add('license', ChoiceType::class, [
                'label' => 'Licence',
                'required' => false,
                'placeholder' => 'Non renseignée',
                'choices' => array_combine(
                    array_map(fn (MediaLicense $l) => $l->name, MediaLicense::cases()),
                    MediaLicense::cases(),
                ),
                'choice_value' => fn (?MediaLicense $license) => $license?->value,
            ])
            ->add('categories', EntityType::class, [
                'label' => 'Rubriques',
                'class' => Category::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Mots-clés',
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ])
            ->add('article', EntityType::class, [
                'label' => 'Article rattaché',
                'class' => Article::class,
                'choice_label' => 'title',
                'required' => false,
                'placeholder' => 'Média autonome, non rattaché à un article',
            ])
            ->add('gallery', EntityType::class, [
                'label' => 'Galerie',
                'class' => Gallery::class,
                'choice_label' => 'title',
                'required' => false,
            ])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('imageUrl', TextType::class, ['label' => 'URL image (type image)', 'required' => false])
            ->add('imageFile', FileType::class, [
                'label' => 'Ou téléversez une image (type image)',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessus.',
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
                    ),
                ],
            ])
            ->add('youtubeEmbedUrl', TextType::class, ['label' => "URL d'embed YouTube (type vidéo)", 'required' => false])
            ->add('thumbnail', TextType::class, [
                'label' => 'URL miniature (type vidéo)',
                'required' => false,
                'help' => "Facultatif : si vous ne fournissez ni URL ni fichier, la miniature par défaut de la vidéo sur YouTube est utilisée automatiquement.",
            ])
            ->add('thumbnailFile', FileType::class, [
                'label' => 'Ou téléversez une miniature (type vidéo)',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessus.',
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
                    ),
                ],
            ])
            ->add('audioUrl', TextType::class, ['label' => 'URL audio (type audio)', 'required' => false])
            ->add('transcript', TextareaType::class, ['label' => 'Transcription (type audio)', 'required' => false])
            ->add('documentUrl', TextType::class, ['label' => 'URL du document (type PDF)', 'required' => false])
            ->add('documentFile', FileType::class, [
                'label' => 'Ou téléversez un document (type PDF)',
                'required' => false,
                'mapped' => false,
                'help' => 'PDF, 20 Mo maximum. Remplace l\'URL ci-dessus.',
                'constraints' => [
                    new File(
                        maxSize: '20M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Veuillez téléverser un fichier PDF valide.',
                    ),
                ],
            ])
        ;

        // Priority 900: the validator's own POST_SUBMIT listener (which
        // checks Media::validateTypeSpecificFields — "image must have a
        // URL or a file", same for the PDF's documentUrl) is registered at
        // the default priority (0) by FormTypeValidatorExtension, and
        // registered *before* this one in the build chain — so without a
        // higher priority here, it would run first and see the pre-upload
        // (empty) imageUrl/documentUrl.
        //
        // Only the URL is assigned here, not the actual file move: moving
        // it now would make the *next* listener (the File constraint on
        // imageFile/documentFile, same priority-0 pass) see an
        // already-consumed UploadedFile and fail with "file could not be
        // uploaded". The physical move happens in MediaController, once
        // isValid() has confirmed every constraint passed, using the same
        // filename (recovered from this URL) so the two never drift apart.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $media = $event->getForm()->getData();
            if (!$media instanceof Media) {
                return;
            }

            $imageFile = $event->getForm()->get('imageFile')->getData();
            if ($imageFile instanceof UploadedFile) {
                $filename = $this->imageUploader->reserveFilename($imageFile);
                $media->setImageUrl($this->imageUploader->urlFor($filename));
            }

            $documentFile = $event->getForm()->get('documentFile')->getData();
            if ($documentFile instanceof UploadedFile) {
                $filename = $this->documentUploader->reserveFilename($documentFile);
                $media->setDocumentUrl($this->documentUploader->urlFor($filename));
            }
        }, 900);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Media::class]);
    }
}
