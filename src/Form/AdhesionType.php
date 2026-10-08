<?php

namespace App\Form;

use App\Entity\Adhesion;
use App\Entity\Licencie;
use App\Entity\Saison;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Licence d'une saison : montant, réduction, règlements échelonnés, aides et observation. */
class AdhesionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['nouveau']) {
            $builder
                ->add('licencie', EntityType::class, [
                    'class'         => Licencie::class,
                    'choice_label'  => static fn (Licencie $l) => sprintf('%s %s (%s)', $l->getNom(), $l->getPrenom(), $l->getFamille()),
                    'query_builder' => static fn ($repo) => $repo->createQueryBuilder('l')->orderBy('l.nom', SortDirection::Ascending)->addOrderBy('l.prenom', SortDirection::Ascending),
                    'label'         => 'Licencié',
                    'required'      => false,
                    'placeholder'   => 'Choisir un licencié',
                    'attr'          => ['data-placeholder' => 'Rechercher un licencié'],
                ])
                ->add('licencieLabel', TextType::class, [
                    'label'    => 'Ou, si la personne n\'est pas encore enregistrée : son nom',
                    'required' => false,
                    'attr'     => ['placeholder' => 'Ex. Jules Martin', 'maxlength' => 150],
                    'help'     => 'La licence sera créée pour ce nom, à rattacher plus tard à une fiche licencié.',
                ])
            ;
        }

        $builder
            ->add('saison', EntityType::class, [
                'class'        => Saison::class,
                'choice_label' => 'libelle',
                'label'        => 'Saison',
                'help'         => 'Une nouvelle saison peut être saisie avant la fin de la précédente : l\'ancienne licence et son suivi sont conservés.',
            ])
            ->add('montantBaseCentimes', MoneyType::class, [
                'label'    => 'Prix de la licence (€)',
                'currency' => false,
                'divisor'  => 100,
            ])
            ->add('reductionCentimes', MoneyType::class, [
                'label'    => 'Réduction (€)',
                'currency' => false,
                'divisor'  => 100,
                'required' => false,
                'help'     => 'Ex. famille de trois ou quatre enfants.',
            ])
            ->add('reductionMotif', TextType::class, [
                'label'    => 'Motif de la réduction',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 3e enfant de la famille'],
            ])
            ->add('reglements', CollectionType::class, [
                'entry_type'     => ReglementType::class,
                'allow_add'      => true,
                'allow_delete'   => true,
                'by_reference'   => false,
                'label'          => false,
                'error_bubbling' => false,
            ])
            ->add('aides', CollectionType::class, [
                'entry_type'     => AideFinanciereType::class,
                'allow_add'      => true,
                'allow_delete'   => true,
                'by_reference'   => false,
                'label'          => false,
                'error_bubbling' => false,
            ])
            ->add('observation', TextareaType::class, [
                'label'    => 'Observations',
                'required' => false,
                'attr'     => ['rows' => 4, 'placeholder' => 'Notes libres : arrangement avec la famille, relances, pièces manquantes…'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Adhesion::class, 'nouveau' => false]);
    }
}
