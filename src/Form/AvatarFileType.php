<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Champ « photo » d'un journaliste, partagé entre la fiche auteur (admin) et
 * la page « Mon profil ». Le fichier n'est pas lié à l'entité : le contrôleur
 * le passe à App\Service\ImageUploader::uploadAvatar().
 */
final class AvatarFileType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'mapped' => false,
            'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/gif'],
            'constraints' => [
                new Image(
                    maxSize: '5M',
                    maxPixels: 25_000_000,
                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    mimeTypesMessage: 'Veuillez téléverser une image valide (JPEG, PNG, WebP ou GIF).',
                    maxPixelsMessage: 'Cette photo est trop grande ({{ pixels }} pixels, {{ max_pixels }} maximum).',
                ),
            ],
        ]);
    }

    public function getParent(): string
    {
        return FileType::class;
    }
}
