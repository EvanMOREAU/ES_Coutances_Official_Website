<?php

namespace App\Form;

use App\Entity\Reglement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Une échéance de règlement : mode, montant, date prévue, encaissement et remise en banque. */
class ReglementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false];

        $builder
            ->add('mode', ChoiceType::class, ['label' => 'Mode', 'choices' => array_flip(Reglement::MODES)])
            ->add('montantCentimes', MoneyType::class, ['label' => 'Montant (€)', 'currency' => false, 'divisor' => 100])
            ->add('dateEcheance', DateType::class, $date + ['label' => 'À encaisser le'])
            ->add('recu', CheckboxType::class, ['label' => 'Encaissé', 'required' => false])
            ->add('dateRemise', DateType::class, $date + ['label' => 'Remise en banque le'])
            ->add('reference', TextType::class, ['label' => 'Référence', 'required' => false, 'attr' => ['placeholder' => 'N° de chèque…', 'maxlength' => 100]])
            ->add('ordre', HiddenType::class, ['empty_data' => '1'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Reglement::class]);
    }
}
