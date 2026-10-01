<?php

namespace App\Form;

use App\Entity\ContratPartenaireTache;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContratPartenaireTacheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Tâche',
                'attr'        => ['placeholder' => 'Ex. Envoyer le flocage, poser le panneau…'],
                'constraints' => [new NotBlank(message: 'Indiquez le libellé de la tâche.'), new Length(max: 150)],
            ])
            ->add('echeance', DateType::class, [
                'label'    => 'Échéance',
                'required' => false,
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
            ])
            ->add('fait', CheckboxType::class, [
                'label'    => 'Faite',
                'required' => false,
            ])
            ->add('ordre', HiddenType::class, ['empty_data' => '0'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContratPartenaireTache::class]);
    }
}
