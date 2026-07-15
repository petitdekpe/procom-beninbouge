<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * The status/publishedAt fields are only exposed to ROLE_ADMIN. Everyone
 * else moves an article through the editorial workflow (Création →
 * Relecture → Correction → Publication) via the dedicated actions in
 * App\Controller\Admin\ArticleController and App\Controller\Admin\WorkflowController
 * instead of picking a status directly — otherwise a journalist could
 * just select "Published" here and skip review entirely.
 */
final class ArticleAdminType extends AbstractType
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'help' => 'Laissé vide : généré automatiquement à partir du titre.',
            ])
            ->add('chapo', TextareaType::class, [
                'label' => 'Chapô',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'data-controller' => 'rich-text',
                    'data-rich-text-upload-url-value' => '/admin/editor/upload-image',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'rows' => 14,
                    'data-controller' => 'rich-text',
                    'data-rich-text-upload-url-value' => '/admin/editor/upload-image',
                    'data-rich-text-size-value' => 'tall',
                ],
            ])
            ->add('coverImage', TextType::class, ['label' => 'URL image de couverture', 'required' => false])
            ->add('coverImageFile', FileType::class, [
                'label' => 'Ou téléversez une image',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessus et apparaît aussi dans Médias.',
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
            ])
            ->add('author', EntityType::class, [
                'label' => 'Auteur',
                'class' => Author::class,
                'choice_label' => fn (Author $author) => trim($author->getFirstName().' '.$author->getLastName()),
                'help' => "Par défaut, l'article est publié sous votre propre nom. Si vous l'attribuez à quelqu'un d'autre — un autre auteur existant, ou un nouveau créé depuis Auteurs > Nouveau — sa diffusion devra être validée par un administrateur.",
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Tags',
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ])
            ->add('featured', CheckboxType::class, ['label' => 'Mis en avant (À la une)', 'required' => false])
            ->add('pinned', CheckboxType::class, ['label' => 'Épinglé (bandeau alerte)', 'required' => false])
        ;

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('status', ChoiceType::class, [
                    'label' => 'Statut',
                    'help' => 'Réservé aux admins : court-circuite le workflow éditorial normal.',
                    'choices' => array_combine(
                        array_map(fn (ArticleStatus $s) => $s->name, ArticleStatus::cases()),
                        ArticleStatus::cases(),
                    ),
                    'choice_value' => fn (?ArticleStatus $status) => $status?->value,
                ])
                ->add('publishedAt', DateTimeType::class, [
                    'label' => 'Date de publication',
                    'required' => false,
                    'widget' => 'single_text',
                    'help' => 'Laissé vide : réglée automatiquement à la publication.',
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}
