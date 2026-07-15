<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Dossier;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class DossierAdminType extends AbstractType
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
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4],
                'help' => "Texte d'introduction affiché en haut de la page du dossier.",
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'Meta titre',
                'required' => false,
                'help' => 'Laisser vide pour le générer automatiquement à partir du titre (60 caractères max).',
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta description',
                'required' => false,
                'attr' => ['rows' => 2],
                'help' => 'Laisser vide pour la générer automatiquement (160 caractères max).',
            ])
            ->add('coverImage', TextType::class, ['label' => 'URL image de couverture', 'required' => false])
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
            ->add('articles', EntityType::class, [
                'label' => 'Articles du dossier',
                'class' => Article::class,
                'choice_label' => 'title',
                'multiple' => true,
                'required' => false,
                'query_builder' => fn (\App\Repository\ArticleRepository $repo): QueryBuilder => $repo->createQueryBuilder('a')->orderBy('a.publishedAt', 'DESC'),
                'help' => "Sélection manuelle : un article peut appartenir à plusieurs dossiers.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Dossier::class]);
    }
}
