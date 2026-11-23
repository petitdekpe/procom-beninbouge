<?php

namespace App\Form;

use App\Entity\Document;
use App\Service\DocumentLibrary;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

final class DocumentAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'];

        $constraints = [DocumentLibrary::fileConstraint()];
        if ($isNew) {
            $constraints[] = new NotNull(message: 'Choisissez un fichier à téléverser.');
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du document',
                'help' => 'Affiché sur le site et utilisé pour le nom du fichier téléchargé.',
            ])
            ->add('file', FileType::class, [
                'label' => $isNew ? 'Fichier' : 'Remplacer le fichier',
                'required' => $isNew,
                'mapped' => false,
                'help' => DocumentLibrary::ALLOWED_FORMATS_LABEL.', '.str_replace('M', ' Mo', DocumentLibrary::MAX_SIZE).' maximum.'
                    .($isNew ? '' : ' Le lien de téléchargement reste le même.'),
                'constraints' => $constraints,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'is_new' => false,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
