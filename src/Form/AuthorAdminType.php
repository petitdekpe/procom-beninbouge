<?php

namespace App\Form;

use App\Entity\Author;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AuthorAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, ['label' => 'E-mail'])
            ->add('bio', TextareaType::class, ['label' => 'Biographie', 'required' => false])
            ->add('avatar', TextType::class, [
                'label' => 'URL de la photo',
                'required' => false,
                'help' => 'Videz ce champ pour retirer la photo.',
            ])
            ->add('avatarFile', AvatarFileType::class, [
                'label' => 'Ou téléversez une photo',
                'help' => 'JPEG, PNG, WebP ou GIF, 5 Mo maximum. Visage centré conseillé : la photo est recadrée en carré. Remplace l\'URL ci-dessus.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Author::class]);
    }
}
