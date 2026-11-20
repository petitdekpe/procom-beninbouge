<?php

namespace App\Form;

use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Recap;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class RecapAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'help' => 'Laissé vide : généré automatiquement à partir du titre.',
            ])
            ->add('videoUrl', TextType::class, [
                'label' => 'Vidéo (lien YouTube ou fichier .mp4 / .webm)',
                'required' => false,
                'help' => 'Vidéo au format mobile (vertical 9:16) : lien YouTube (Short compris) ou lien direct vers un fichier.',
            ])
            ->add('videoFile', FileType::class, [
                'label' => 'Ou téléversez une vidéo',
                'required' => false,
                'mapped' => false,
                'help' => 'MP4 ou WebM, 50 Mo maximum. Remplace le lien ci-dessus.',
                'constraints' => [
                    new File(
                        maxSize: '50M',
                        mimeTypes: ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v'],
                        mimeTypesMessage: 'Veuillez téléverser une vidéo valide (MP4 ou WebM).',
                    ),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Texte',
                'attr' => [
                    'rows' => 12,
                    'data-controller' => 'rich-text',
                    'data-rich-text-upload-url-value' => '/admin/editor/upload-image',
                    'data-rich-text-size-value' => 'tall',
                ],
            ])
            ->add('coverImage', TextType::class, [
                'label' => 'URL image de couverture',
                'required' => false,
                'help' => 'Visuel des cartes et affiche de la vidéo. Laissé vide : miniature YouTube.',
            ])
            ->add('coverImageFile', FileType::class, [
                'label' => 'Ou téléversez une image',
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
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Aucune',
            ])
            ->add('author', EntityType::class, [
                'label' => 'Auteur',
                'class' => Author::class,
                'choice_label' => fn (Author $author) => trim($author->getFirstName().' '.$author->getLastName()),
                'required' => false,
                'placeholder' => 'Aucun (signé du nom du site)',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => ArticleStatus::Draft,
                    'Publié' => ArticleStatus::Published,
                ],
                'choice_value' => fn (?ArticleStatus $status) => $status?->value,
                'help' => 'Un récap publié apparaît sur l\'accueil (section « Récap ») et sur sa propre page.',
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Laissé vide : réglée automatiquement à la publication.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Recap::class]);
    }
}
