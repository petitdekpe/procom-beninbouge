<?php

namespace App\Form;

use App\Entity\Category;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CategoryAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Category $category */
        $category = $options['data'];

        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('slug', TextType::class, ['label' => 'Slug'])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
                'help' => "Affichée en intro sur la page publique de la rubrique, et utilisée pour la méta-description si celle-ci est laissée vide.",
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'Meta titre',
                'required' => false,
                'help' => 'Laisser vide pour le générer automatiquement à partir du nom (60 caractères max).',
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta description',
                'required' => false,
                'attr' => ['rows' => 2],
                'help' => 'Laisser vide pour la générer automatiquement (160 caractères max).',
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
                'help' => "Utilisée pour l'étiquette de la rubrique (articles, médias, page publique).",
            ])
            ->add('parent', EntityType::class, [
                'label' => 'Catégorie parente',
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Aucune (catégorie de premier niveau) —',
                'help' => 'Laisser vide pour une catégorie de premier niveau.',
                'query_builder' => function (\App\Repository\CategoryRepository $repo) use ($category): QueryBuilder {
                    $qb = $repo->createQueryBuilder('c')->orderBy('c.name', 'ASC');

                    // A category can't be its own parent, nor a sub-category
                    // of one of its own descendants (see Category::validateParent).
                    if (null !== $category->getId()) {
                        $qb->andWhere('c != :self')->setParameter('self', $category);
                    }

                    return $qb;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Category::class]);
    }
}
