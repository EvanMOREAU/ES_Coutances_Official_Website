<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<mixed> */
class AppearanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('theme', ChoiceType::class, [
                'label'    => 'Thème',
                'choices'  => [
                    'Clair'   => 'light',
                    'Sombre'  => 'dark',
                    'Système' => 'system',
                ],
                'expanded' => true,
            ])
            ->add('colorScheme', ChoiceType::class, [
                'label'    => 'Couleur d\'accent',
                'choices'  => [
                    'Rouge ES Coutances' => 'rouge',
                    'Violet'  => 'violet',
                    'Bleu'    => 'blue',
                    'Émeraude' => 'emerald',
                    'Rose'    => 'rose',
                    'Orange'  => 'orange',
                    'Ardoise' => 'slate',
                ],
                'expanded' => true,
            ])
            ->add('density', ChoiceType::class, [
                'label'    => 'Densité',
                'choices'  => [
                    'Compacte'    => 'compact',
                    'Confortable' => 'comfortable',
                    'Spacieuse'   => 'spacious',
                ],
                'expanded' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
