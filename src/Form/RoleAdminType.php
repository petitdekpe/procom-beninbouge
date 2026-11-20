<?php

namespace App\Form;

use App\Entity\Role;
use App\Security\Permission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RoleAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Nom du rôle',
                'help' => 'Ce nom apparaît dans la liste des utilisateurs, par exemple « Gestionnaire de publicités ».',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
        ;

        if ($options['lock_permissions']) {
            return;
        }

        /** @var list<string> $grantable permissions que l'auteur de la modification peut accorder */
        $grantable = $options['grantable_permissions'];

        $choices = [];
        foreach (Permission::grouped() as $group => $permissions) {
            foreach ($permissions as $permission) {
                $choices[$group][$permission->label()] = $permission->value;
            }
        }

        $builder->add('permissions', ChoiceType::class, [
            'label' => 'Permissions',
            'choices' => $choices,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choice_attr' => static fn (string $value): array => \in_array($value, $grantable, true) ? [] : ['disabled' => 'disabled'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'lock_permissions' => false,
            'grantable_permissions' => [],
        ]);
        $resolver->setAllowedTypes('lock_permissions', 'bool');
        $resolver->setAllowedTypes('grantable_permissions', 'array');
    }
}
