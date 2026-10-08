<?php

namespace App\Form;

use App\Entity\SlideCarousel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractType<mixed> */
class SlideCarouselType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre'])
            ->add('titreMot', TextType::class, ['label' => 'Mot mis en rouge', 'required' => false])
            ->add('sousTitre', TextType::class, ['label' => 'Sous-titre', 'required' => false])
            ->add('imageFile', VichImageType::class, [
                'label'    => 'Image',
                'required' => $options['is_new'],
            ])
            ->add('actif', CheckboxType::class, ['label' => 'Actif', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SlideCarousel::class,
            'is_new'     => false,
        ]);
    }
}
