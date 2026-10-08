<?php

namespace App\Form;

use App\Entity\OffreEmploi;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractType<mixed> */
class OffreEmploiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre'])
            ->add('type', ChoiceType::class, [
                'label'   => 'Type',
                'choices' => [
                    'Alternance'      => 'alternance',
                    'Service civique' => 'service-civique',
                    'BPJEPS'          => 'bpjeps',
                    'CDI'             => 'cdi',
                    'CDD'             => 'cdd',
                ],
            ])
            ->add('description', TextareaType::class, ['label' => 'Description'])
            ->add('imageFile', VichImageType::class, ['label' => 'Image', 'required' => false])
            ->add('actif', CheckboxType::class, ['label' => 'Actif', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OffreEmploi::class,
        ]);
    }
}
