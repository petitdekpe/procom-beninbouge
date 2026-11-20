<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Changement de son propre mot de passe : l'actuel (ou le temporaire reçu
 * par e-mail) puis le nouveau, saisi deux fois.
 */
final class PasswordChangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel (ou temporaire)',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'Saisissez votre mot de passe actuel.'),
                    new UserPassword(message: 'Ce mot de passe actuel est incorrect.'),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'help' => '10 caractères minimum, avec au moins une lettre et un chiffre.',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Les deux mots de passe ne sont pas identiques.',
                'constraints' => [
                    new NotBlank(message: 'Choisissez un nouveau mot de passe.'),
                    new Length(min: 10, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.', max: 4096),
                    new Regex(pattern: '/(?=.*\p{L})(?=.*\d)/u', message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.'),
                ],
            ])
        ;
    }
}
