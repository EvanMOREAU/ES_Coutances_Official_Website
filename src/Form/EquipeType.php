<?php

namespace App\Form;

use App\Entity\Equipe;
use App\Service\CategorieAge;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', ChoiceType::class, [
                'label'   => 'Catégorie d\'âge',
                'choices' => CategorieAge::choices(),
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'équipe',
                'attr'  => ['placeholder' => 'Ex. U11 A, U11 B'],
            ])
            ->add('statut', StatutChoiceType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Equipe::class]);
    }
}
