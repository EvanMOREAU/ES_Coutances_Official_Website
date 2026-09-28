<?php

namespace App\Form;

use App\Entity\Equipe;
use App\Entity\Saison;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use App\Service\CategorieAge;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une "ligne" licencié dans le wizard de création d'une famille. Pas liée à
 * l'entité Licencie directement (data_class null, tableau en sortie) : la
 * famille et le compte utilisateur ne sont créés qu'à la validation finale
 * du wizard, dans le contrôleur.
 */
class LicencieRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('prenom', TextType::class, ['label' => 'Prénom'])
            ->add('dateNaissance', DateType::class, [
                'label'  => 'Date de naissance',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'attr'   => ['data-start-view' => 'years'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email du licencié',
                'help'  => 'Sert à créer son propre compte sur l\'espace en ligne.',
            ])
            ->add('saison', EntityType::class, [
                'class'        => Saison::class,
                'choice_label' => 'libelle',
                'label'        => 'Saison',
                'choice_attr'  => static fn (Saison $s) => ['data-year' => $s->getDateFin()?->format('Y')],
            ])
            ->add('decalageCategorie', ChoiceType::class, [
                'label'   => "Catégorie d'âge",
                'choices' => CategorieAge::decalageChoices(),
                'help'    => "Calculée automatiquement d'après la date de naissance.",
            ])
            ->add('equipes', EntityType::class, [
                'class'        => Equipe::class,
                'choice_label' => 'nom',
                'group_by'     => 'categorie',
                'choice_attr'  => static fn (Equipe $e) => ['data-categorie' => $e->getCategorie()],
                'label'        => 'Équipes',
                'multiple'     => true,
                'attr'         => ['data-placeholder' => 'Choisir une ou plusieurs équipes'],
                'required'     => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
