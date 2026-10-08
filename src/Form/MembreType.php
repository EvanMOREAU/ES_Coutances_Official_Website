<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Membre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractType<mixed> */
class MembreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom complet'])
            ->add('poste', TextType::class, ['label' => 'Intitulé du poste'])
            ->add('diplome', TextType::class, ['label' => 'Diplôme', 'required' => false])
            ->add('categories', EntityType::class, [
                'class'        => Categorie::class,
                'choice_label' => 'nom',
                'label'        => 'Catégories',
                'multiple'     => true,
                'attr'         => ['data-placeholder' => 'Choisir une ou plusieurs catégories'],
                'by_reference' => false,
            ])
            ->add('photoFile', VichImageType::class, ['label' => 'Photo', 'required' => false])
            ->add('ordre', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'help'  => 'Rempli automatiquement. Modifier uniquement si nécessaire.',
            ])
            ->add('actif', CheckboxType::class, ['label' => 'Actif', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Membre::class,
        ]);
    }
}
