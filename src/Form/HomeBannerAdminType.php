<?php

namespace App\Form;

use App\Entity\HomeBanner;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class HomeBannerAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'help' => 'Nom de la publicité dans l\'admin et texte alternatif de l\'image (lu par les lecteurs d\'écran).',
            ])
            ->add('advertiser', TextType::class, [
                'label' => 'Annonceur',
                'help' => 'L\'entreprise ou l\'organisation qui paie cette publicité.',
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image (téléversement)',
                'required' => false,
                'mapped' => false,
                'help' => 'Image rectangulaire, de préférence 1600 × 500 px (format panoramique 16:5). JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessous.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/gif'],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
                    ),
                ],
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'Ou URL de l\'image',
                'required' => false,
            ])
            ->add('linkUrl', TextType::class, [
                'label' => 'Lien au clic (facultatif)',
                'required' => false,
                'help' => 'https://… pour le site de l\'annonceur, ou /article/mon-article pour une page du site. Vide : l\'image n\'est pas cliquable.',
            ])
            ->add('startsAt', DateTimeType::class, [
                'label' => 'Début de la diffusion',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Dans le futur : la publicité est programmée et apparaît à cette date.',
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => 'Durée (jours)',
                'attr' => ['min' => 1, 'max' => 730],
                'help' => 'La fin de diffusion est calculée à partir du début. Allonger la durée d\'une publicité terminée la remet en ligne.',
            ])
            ->add('amount', IntegerType::class, [
                'label' => 'Montant facturé (FCFA)',
                'attr' => ['min' => 0],
                'help' => 'À titre de suivi : le paiement se fait en dehors du site.',
            ])
            ->add('contactName', TextType::class, ['label' => 'Contact chez l\'annonceur', 'required' => false])
            ->add('contactEmail', TextType::class, ['label' => 'E-mail du contact', 'required' => false])
            ->add('contactPhone', TextType::class, ['label' => 'Téléphone du contact', 'required' => false])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Active (décocher pour mettre en pause)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => HomeBanner::class]);
    }
}
