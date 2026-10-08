<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FamilleWizardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la famille',
                'constraints' => [new Assert\NotBlank(message: 'Indiquez le nom de la famille.'), new Assert\Length(max: 150)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email de référence de la famille',
                'constraints' => [new Assert\NotBlank(message: 'Indiquez l\'adresse e-mail de la famille.'), new Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.')],
                'help'  => 'Un compte est créé automatiquement à cette adresse : la famille reçoit un email pour définir son mot de passe.',
            ])
            ->add('prenomReferent', TextType::class, [
                'label'    => 'Prénom du parent référent',
                'required' => false,
                'help'     => 'Affiché entre parenthèses devant le nom de famille pour distinguer deux familles homonymes.',
            ])
            ->add('adresse', TextType::class, [
                'label'    => 'Adresse',
                'required' => false,
            ])
            ->add('codePostal', TextType::class, [
                'label'    => 'Code postal',
                'required' => false,
            ])
            ->add('ville', TextType::class, [
                'label'    => 'Ville',
                'required' => false,
            ])
            ->add('telephone', TextType::class, [
                'label'    => 'Téléphone',
                'required' => false,
            ])
            ->add('licencies', CollectionType::class, [
                'label'        => false,
                'entry_type'   => LicencieRowType::class,
                'allow_add'    => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype'    => true,
                'required'     => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
