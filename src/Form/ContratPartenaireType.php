<?php

namespace App\Form;

use App\Entity\ContratPartenaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Contrat d'un partenaire : intitulé, montant, période, règlements échelonnés et tâches. */
class ContratPartenaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = ['widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false];

        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Intitulé',
                'attr'        => ['placeholder' => 'Ex. Partenariat saison 2025-2026, panneau publicitaire…'],
                'constraints' => [new NotBlank(message: 'Donnez un intitulé au contrat.'), new Length(max: 150)],
            ])
            ->add('montantCentimes', MoneyType::class, [
                'label'    => 'Montant du contrat (€)',
                'currency' => false,
                'divisor'  => 100,
            ])
            ->add('dateDebut', DateType::class, $date + ['label' => 'Début'])
            ->add('dateFin', DateType::class, $date + ['label' => 'Fin'])
            ->add('reglements', CollectionType::class, [
                'entry_type'     => ContratPartenaireReglementType::class,
                'allow_add'      => true,
                'allow_delete'   => true,
                'by_reference'   => false,
                'label'          => false,
                'error_bubbling' => false,
            ])
            ->add('taches', CollectionType::class, [
                'entry_type'     => ContratPartenaireTacheType::class,
                'allow_add'      => true,
                'allow_delete'   => true,
                'by_reference'   => false,
                'label'          => false,
                'error_bubbling' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label'    => 'Notes',
                'required' => false,
                'attr'     => ['rows' => 4, 'placeholder' => 'Notes libres : contact, engagements particuliers…'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContratPartenaire::class]);
    }
}
