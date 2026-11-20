<?php

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Création et modification d'un compte depuis /admin/users. Le mot de passe
 * n'est jamais saisi ici : il est généré et envoyé par e-mail
 * (App\Service\TemporaryPasswordMailer).
 */
final class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Role> $roles */
        $roles = $options['assignable_roles'];

        $choices = [];
        foreach ($roles as $role) {
            $choices[(string) $role->getLabel()] = (string) $role->getCode();
        }

        $builder
            ->add('displayName', TextType::class, ['label' => 'Nom affiché'])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'help' => 'Elle sert d\'identifiant de connexion, et c\'est là que le mot de passe temporaire est envoyé.',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => $choices,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_attr' => static function (string $code) use ($roles): array {
                    foreach ($roles as $role) {
                        if ($role->getCode() === $code) {
                            return ['data-description' => (string) $role->getDescription()];
                        }
                    }

                    return [];
                },
                'help' => 'Un compte sans rôle peut seulement commenter sur le site public. Les permissions de chaque rôle se règlent dans « Rôles et permissions ».',
            ])
        ;

        if ($options['allow_enabled']) {
            $builder->add('enabled', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
                'help' => 'Décoché : la connexion est refusée, sans supprimer le compte ni son historique.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            // Validation par défaut sans le mot de passe (groupe « user:write »).
            'validation_groups' => ['Default'],
            'assignable_roles' => [],
            'allow_enabled' => true,
        ]);
        $resolver->setAllowedTypes('assignable_roles', 'array');
        $resolver->setAllowedTypes('allow_enabled', 'bool');
    }
}
