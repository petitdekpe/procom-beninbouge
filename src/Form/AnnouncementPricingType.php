<?php

namespace App\Form;

use App\Entity\SiteSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Announcement pricing, stored on SiteSettings (see App\Service\AnnouncementPricing).
 */
final class AnnouncementPricingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('announcementDailyPrice', IntegerType::class, [
                'label' => 'Page Annonces (FCFA par jour)',
                'help' => 'Toujours inclus : l\'annonce apparaît sur la page Annonces & Opportunités.',
                'attr' => ['min' => 0, 'step' => 50],
            ])
            ->add('announcementHomeCardDailyPrice', IntegerType::class, [
                'label' => 'Option carte sur l\'accueil (FCFA par jour, en plus)',
                'attr' => ['min' => 0, 'step' => 50],
            ])
            ->add('announcementPopupDailyPrice', IntegerType::class, [
                'label' => 'Option pop-up sur l\'accueil (FCFA par jour, en plus)',
                'attr' => ['min' => 0, 'step' => 50],
            ])
            ->add('announcementMinDays', IntegerType::class, [
                'label' => 'Durée minimale (jours)',
                'attr' => ['min' => 1, 'max' => 365],
            ])
            ->add('announcementMaxDays', IntegerType::class, [
                'label' => 'Durée maximale (jours)',
                'attr' => ['min' => 1, 'max' => 365],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SiteSettings::class]);
    }
}
