<?php

namespace App\Form;

use App\Entity\ProfilAutorisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Nom et description d'un profil ; les autorisations sont cochées dans la matrice (champ permissions[]). */
class ProfilAutorisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom du profil', 'attr' => ['placeholder' => 'Ex. Secrétariat']])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => false, 'attr' => ['placeholder' => 'À quoi sert ce profil ?', 'maxlength' => 255]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProfilAutorisation::class]);
    }
}
