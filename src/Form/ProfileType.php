<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/** @extends AbstractType<mixed> */
class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Les comptes clients de la boutique n'ont pas de photo de profil (option with_avatar).
        if ($options['with_avatar']) {
            $builder->add('avatarFile', FileType::class, [
                'label'       => 'Avatar',
                'required'    => false,
                'constraints' => [new Image(
                    maxSize: '2M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/gif'],
                    maxSizeMessage: 'L\'image est trop lourde (2 Mo maximum).',
                    mimeTypesMessage: 'Formats acceptés : JPG, PNG ou GIF.',
                )],
            ]);
        }
        $builder
            ->add('prenom', TextType::class, ['label' => 'Prénom', 'required' => false])
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, ['label' => 'Adresse e-mail'])
        ;
        if ($options['with_bio']) {
            $builder->add('bio', TextareaType::class, [
                'label'    => 'Bio',
                'required' => false,
                'attr'     => ['rows' => 4, 'placeholder' => 'Quelques mots sur vous…'],
            ]);
        }
        // Espace « Mon compte » : changer d'adresse e-mail exige le mot de passe actuel.
        if ($options['confirm_password']) {
            $builder->add('currentPassword', PasswordType::class, [
                'mapped'   => false,
                'required' => false,
                'label'    => 'Mot de passe actuel (pour changer d\'adresse e-mail)',
                'attr'     => ['autocomplete' => 'current-password'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'with_avatar' => true, 'with_bio' => true, 'confirm_password' => false]);
    }
}
