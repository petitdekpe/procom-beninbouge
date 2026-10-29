<?php

namespace App\Form;

use App\Entity\TeamMember;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class TeamMemberAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom et prénom',
                'attr' => ['placeholder' => 'Ex. Awa Houngbo'],
            ])
            ->add('role', TextType::class, [
                'label' => 'Fonction',
                'attr' => ['placeholder' => 'Ex. Rédactrice en chef'],
            ])
            ->add('photo', TextType::class, [
                'label' => 'URL de la photo',
                'required' => false,
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Ou téléversez une photo',
                'required' => false,
                'mapped' => false,
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Format portrait conseillé. Remplace l\'URL ci-dessus.',
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
                    ),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Présentation',
                'required' => false,
                'attr' => ['rows' => 5],
                'help' => 'Affichée quand on clique sur la carte du membre (1000 caractères maximum).',
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail public',
                'required' => false,
            ])
            ->add('linkedinUrl', UrlType::class, [
                'label' => 'LinkedIn',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://www.linkedin.com/in/…'],
            ])
            ->add('twitterUrl', UrlType::class, [
                'label' => 'X / Twitter',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://x.com/…'],
            ])
            ->add('facebookUrl', UrlType::class, [
                'label' => 'Facebook',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://www.facebook.com/…'],
            ])
            ->add('visible', CheckboxType::class, [
                'label' => 'Afficher sur la page À propos',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TeamMember::class]);
    }
}
