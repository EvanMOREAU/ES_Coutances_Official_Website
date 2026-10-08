<?php

namespace App\Form;

use App\Entity\ContratPartenaireReglement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<mixed> */
class ContratPartenaireReglementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false];

        $builder
            ->add('mode', ChoiceType::class, [
                'label'   => 'Mode',
                'choices' => array_flip(ContratPartenaireReglement::MODES),
            ])
            ->add('montantCentimes', MoneyType::class, [
                'label'    => 'Montant (€)',
                'currency' => false,
                'divisor'  => 100,
            ])
            ->add('dateEcheance', DateType::class, $date + ['label' => 'Échéance'])
            ->add('recu', CheckboxType::class, [
                'label'    => 'Encaissé',
                'required' => false,
            ])
            ->add('dateRemise', DateType::class, $date + ['label' => 'Date d\'encaissement'])
            ->add('reference', TextType::class, [
                'label'    => 'Référence',
                'required' => false,
                'attr'     => ['placeholder' => 'N° de chèque, référence du virement…', 'maxlength' => 100],
            ])
            ->add('ordre', HiddenType::class, ['empty_data' => '1'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContratPartenaireReglement::class]);
    }
}
