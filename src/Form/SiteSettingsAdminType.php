<?php

namespace App\Form;

use App\Entity\SiteSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class SiteSettingsAdminType extends AbstractType
{
    private const IMAGE_CONSTRAINTS = [
        'maxSize' => '5M',
        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'mimeTypesMessage' => 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ----- Nom du site -----
            ->add('siteName', TextType::class, ['label' => 'Nom du site'])

            // ----- Logo / favicon -----
            ->add('logoUrl', TextType::class, ['label' => 'URL du logo (en-tête, fond clair)', 'required' => false])
            ->add('logoFile', FileType::class, [
                'label' => 'Ou téléversez un logo (en-tête)',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessus.',
                'constraints' => [new File(...self::IMAGE_CONSTRAINTS)],
            ])
            ->add('logoFooterUrl', TextType::class, ['label' => 'URL du logo (pied de page, fond sombre)', 'required' => false])
            ->add('logoFooterFile', FileType::class, [
                'label' => 'Ou téléversez un logo (pied de page)',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessus.',
                'constraints' => [new File(...self::IMAGE_CONSTRAINTS)],
            ])
            ->add('faviconUrl', TextType::class, ['label' => 'URL du favicon', 'required' => false])
            ->add('faviconFile', FileType::class, [
                'label' => 'Ou téléversez un favicon',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessus.',
                'constraints' => [new File(...self::IMAGE_CONSTRAINTS)],
            ])

            // ----- Aperçu des liens partagés -----
            ->add('shareImageUrl', TextType::class, [
                'label' => 'URL de l\'image d\'aperçu des liens',
                'required' => false,
                'help' => 'Vignette affichée quand un lien du site est partagé (WhatsApp, Facebook, X, LinkedIn…) depuis une page sans image propre : accueil, rubriques, pages… Les articles gardent leur image de couverture. Vide : le logo est utilisé.',
            ])
            ->add('shareImageFile', FileType::class, [
                'label' => 'Ou téléversez l\'image d\'aperçu',
                'required' => false,
                'mapped' => false,
                'help' => 'Format idéal : 1200 × 630 px (paysage). JPEG, PNG, WebP ou GIF, 5 Mo maximum. Remplace l\'URL ci-dessus.',
                'constraints' => [new File(...self::IMAGE_CONSTRAINTS)],
            ])

            // ----- Pied de page -----
            ->add('footerTagline', TextareaType::class, [
                'label' => 'Texte du pied de page',
                'required' => false,
                'attr' => ['rows' => 3],
            ])

            // ----- Contacts -----
            ->add('contactAddress', TextType::class, ['label' => 'Adresse', 'required' => false])
            ->add('contactPhone', TextType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('contactEmail', TextType::class, ['label' => 'E-mail de contact (affiché publiquement)', 'required' => false])

            // ----- Emails -----
            ->add('emailFromName', TextType::class, [
                'label' => "Nom d'expéditeur",
                'required' => false,
                'help' => "Utilisé pour les e-mails envoyés par le site une fois cette fonctionnalité activée (aucun envoi automatique n'existe encore).",
            ])
            ->add('emailFromAddress', TextType::class, ['label' => "Adresse d'expédition", 'required' => false])

            // ----- Réseaux sociaux -----
            ->add('facebookUrl', TextType::class, ['label' => 'Facebook', 'required' => false])
            ->add('twitterUrl', TextType::class, ['label' => 'X / Twitter', 'required' => false])
            ->add('instagramUrl', TextType::class, ['label' => 'Instagram', 'required' => false])
            ->add('youtubeUrl', TextType::class, ['label' => 'YouTube', 'required' => false])
            ->add('whatsappUrl', TextType::class, [
                'label' => 'WhatsApp',
                'required' => false,
                'help' => 'Lien complet, ex. https://wa.me/22900000000',
            ])

            // ----- Référencement & mesure d'audience -----
            ->add('googleAnalyticsId', TextType::class, [
                'label' => 'Identifiant Google Analytics 4',
                'required' => false,
                'attr' => ['placeholder' => 'G-XXXXXXXXXX'],
                'help' => 'Google Analytics → Administration → Flux de données → Web. Vide : aucun suivi. Le suivi ne s\'applique qu\'aux pages publiques, pas à l\'administration. Dans les mesures améliorées du flux, décochez « Modifications de page en fonction des événements de l\'historique du navigateur », sinon les pages vues sont comptées en double.',
            ])
            ->add('googleSiteVerification', TextType::class, [
                'label' => 'Code de validation Google Search Console',
                'required' => false,
                'help' => 'Search Console → Ajouter une propriété → Préfixe d\'URL → Balise HTML. Vous pouvez coller la balise entière, seule la valeur est conservée.',
            ])

            // ----- Maintenance -----
            ->add('maintenanceMode', CheckboxType::class, [
                'label' => 'Activer le mode maintenance',
                'required' => false,
                'help' => 'Le site public affiche une page d\'indisponibilité à tous les visiteurs sauf aux administrateurs connectés.',
            ])
            ->add('maintenanceMessage', TextareaType::class, [
                'label' => 'Message affiché pendant la maintenance',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SiteSettings::class]);
    }
}
