<?php

namespace App\Form;

use App\Entity\Page;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PageAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'help' => 'Laissé vide : généré automatiquement à partir du titre. URL finale : /page/&lt;slug&gt;',
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
            ->add('metaTitle', TextType::class, [
                'label' => 'Titre SEO',
                'required' => false,
                'help' => 'Laissé vide : généré automatiquement à partir du titre.',
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Description SEO',
                'required' => false,
                'attr' => ['rows' => 2],
                'help' => 'Laissé vide : généré automatiquement à partir du contenu.',
            ])
            ->add('published', CheckboxType::class, ['label' => 'Publiée', 'required' => false])
            ->add('position', IntegerType::class, [
                'label' => 'Ordre dans le pied de page',
                'required' => false,
                'help' => 'Les pages sont listées par ordre croissant, les plus petits nombres en premier.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Page::class]);
    }
}
