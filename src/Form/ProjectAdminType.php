<?php

namespace App\Form;

use App\Entity\Enum\ProjectProgress;
use App\Entity\Enum\ProjectType;
use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProjectAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('porteur', TextType::class, ['label' => 'Porteur du projet'])
            ->add('budget', MoneyType::class, ['label' => 'Budget (FCFA)', 'currency' => false, 'required' => false])
            ->add('progress', ChoiceType::class, [
                'label' => "État d'avancement",
                'choices' => array_combine(
                    array_map(fn (ProjectProgress $p) => $p->name, ProjectProgress::cases()),
                    ProjectProgress::cases(),
                ),
                'choice_value' => fn (?ProjectProgress $progress) => $progress?->value,
            ])
            ->add('location', TextType::class, ['label' => 'Localisation'])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => array_combine(
                    array_map(fn (ProjectType $t) => $t->name, ProjectType::cases()),
                    ProjectType::cases(),
                ),
                'choice_value' => fn (?ProjectType $type) => $type?->value,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Project::class]);
    }
}
