<?php

namespace App\Form;

use App\Entity\AideFinanciere;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Une aide (Pass'Sport, Spot 50…) : montant attendu, puis reçu ou non. */
class AideFinanciereType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, ['label' => 'Aide', 'choices' => array_flip(AideFinanciere::TYPES)])
            ->add('montantCentimes', MoneyType::class, ['label' => 'Montant (€)', 'currency' => false, 'divisor' => 100])
            ->add('statut', ChoiceType::class, [
                'label'   => 'État',
                'choices' => ['Attendue' => AideFinanciere::STATUT_ATTENDUE, 'Reçue' => AideFinanciere::STATUT_RECUE],
            ])
            ->add('dateReception', DateType::class, ['label' => 'Reçue le', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('note', TextType::class, ['label' => 'Note', 'required' => false, 'attr' => ['placeholder' => 'N° de dossier, relance…', 'maxlength' => 255]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AideFinanciere::class]);
    }
}
