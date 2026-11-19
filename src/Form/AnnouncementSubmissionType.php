<?php

namespace App\Form;

use App\Entity\Announcement;
use App\Entity\Enum\AnnouncementCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Public « Publier une annonce » form (App\Controller\AnnouncementController::submit).
 * min_days / max_days come from the admin pricing settings (App\Service\AnnouncementPricing).
 *
 * staff: true is the admin variant (App\Controller\Admin\AnnouncementController::new/edit):
 * no publication terms to accept, the duration counts from publication, the
 * description gets the rich-text editor, contact details are optional and a
 * Word document may be attached as well as a PDF.
 */
final class AnnouncementSubmissionType extends AbstractType
{
    private const PDF = ['application/pdf', 'application/x-pdf'];
    private const WORD = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $staff = $options['staff'];
        // Coordonnées : obligatoires pour l'annonceur (paiement, suivi), facultatives pour la rédaction.
        $required = static fn (string $message): array => $staff ? [] : [new NotBlank(message: $message)];
        $documentTypes = $staff ? [...self::PDF, ...self::WORD] : self::PDF;

        $builder
            ->add('category', EnumType::class, [
                'class' => AnnouncementCategory::class,
                'label' => 'Catégorie',
                'choice_label' => static fn (AnnouncementCategory $category) => $category->label(),
                'placeholder' => 'Choisissez une catégorie',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex. Recrutement d\'un chargé de communication', 'maxlength' => 120],
            ])
            ->add('organization', TextType::class, [
                'label' => 'Organisation ou annonceur',
                'attr' => ['maxlength' => 120],
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Cotonou', 'maxlength' => 120],
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Date limite',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Date limite de candidature, de dépôt ou date de l\'événement (facultatif).',
                'constraints' => [new GreaterThanOrEqual('today', message: 'La date limite ne peut pas être passée.')],
            ])
            ->add('description', TextareaType::class, $staff ? [
                'label' => 'Description',
                'help' => 'Au moins 30 caractères. Gras, italique, titres, listes et liens sont conservés.',
                'attr' => [
                    'rows' => 10,
                    'data-controller' => 'rich-text',
                    'data-rich-text-upload-url-value' => '/admin/editor/upload-image',
                ],
            ] : [
                'label' => 'Description',
                'attr' => ['rows' => 8, 'maxlength' => 3000],
                'help' => 'Entre 30 et 3000 caractères. Décrivez l\'offre, les conditions et la marche à suivre.',
                'constraints' => [new Length(max: 3000, maxMessage: 'La description ne peut pas dépasser 3000 caractères.')],
            ])
            ->add('externalUrl', UrlType::class, [
                'label' => 'Lien vers plus d\'informations',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://…'],
                'help' => 'Facultatif, comme le document ci-dessous : l\'un, l\'autre ou les deux.',
            ])
            ->add('documentFile', FileType::class, [
                'label' => 'Ou un document à télécharger',
                'required' => false,
                'mapped' => false,
                'help' => $staff ? 'PDF ou Word, 10 Mo maximum (facultatif).' : 'PDF, 10 Mo maximum (facultatif).',
                'attr' => ['accept' => $staff ? '.pdf,.doc,.docx,application/pdf' : '.pdf,application/pdf'],
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: $documentTypes,
                        mimeTypesMessage: $staff ? 'Veuillez téléverser un document PDF ou Word.' : 'Veuillez téléverser un document PDF.',
                    ),
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image ou affiche',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG ou WebP, 5 Mo maximum (facultatif).',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG ou WebP).',
                    ),
                ],
            ])
            ->add('contactName', TextType::class, [
                'label' => 'Nom du contact',
                'required' => !$staff,
                'attr' => ['autocomplete' => 'name'],
                'constraints' => $required('Le nom du contact est requis.'),
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'E-mail du contact',
                'required' => !$staff,
                'attr' => ['autocomplete' => 'email'],
                'constraints' => $required('L\'e-mail du contact est requis.'),
            ])
            ->add('contactPhone', TelType::class, [
                'label' => 'Téléphone du contact',
                'required' => !$staff,
                'attr' => ['autocomplete' => 'tel', 'placeholder' => '+229 01 XX XX XX XX'],
                'constraints' => $required('Le téléphone du contact est requis.'),
            ])
            ->add('showContact', CheckboxType::class, [
                'label' => 'Afficher ces coordonnées sur l\'annonce publiée',
                'required' => false,
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => 'Durée d\'affichage (jours)',
                'attr' => ['min' => $options['min_days'], 'max' => $options['max_days']],
                'help' => sprintf('De %d à %d jours, à compter de %s.', $options['min_days'], $options['max_days'], $staff ? 'la publication' : 'la validation de l\'annonce'),
                'constraints' => [
                    new NotNull(message: 'Indiquez une durée.'),
                    new Range(min: $options['min_days'], max: $options['max_days'], notInRangeMessage: 'La durée doit être comprise entre {{ min }} et {{ max }} jours.'),
                ],
            ])
            ->add('withHomeCard', CheckboxType::class, [
                'label' => 'Carte sur la page d\'accueil',
                'required' => false,
            ])
            ->add('withPopup', CheckboxType::class, [
                'label' => 'Pop-up sur la page d\'accueil',
                'required' => false,
            ])
        ;

        if ($staff) {
            return;
        }

        $builder
            ->add('acceptTerms', CheckboxType::class, [
                'label' => 'J\'accepte que l\'annonce soit validée par la rédaction avant publication (sous 48 h) et qu\'elle puisse être refusée si elle ne respecte pas la ligne éditoriale.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'Vous devez accepter les conditions de publication.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Announcement::class,
            'min_days' => 3,
            'max_days' => 60,
            'staff' => false,
        ]);
        $resolver->setAllowedTypes('min_days', 'int');
        $resolver->setAllowedTypes('max_days', 'int');
        $resolver->setAllowedTypes('staff', 'bool');
    }
}
