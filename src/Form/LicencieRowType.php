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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une "ligne" licencié dans le wizard de création d'une famille. Pas liée à
 * l'entité Licencie directement (data_class null, tableau en sortie) : la
 * famille et le compte utilisateur ne sont créés qu'à la validation finale
 * du wizard, dans le contrôleur.
 *
 * Comme data_class est null, Symfony ne dérive aucune contrainte à partir du
 * mapping Doctrine (pas de NotBlank implicite sur les colonnes non-nullable) :
 * les champs obligatoires doivent donc porter leur contrainte explicitement,
 * sous peine de crasher plus loin (setter d'entité non-nullable appelé avec
 * null) au lieu d'afficher une erreur de formulaire.
 */
class LicencieRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => 'Nom',
                'constraints' => [new Assert\NotBlank(message: 'Le nom du licencié est obligatoire.')],
            ])
            ->add('prenom', TextType::class, [
                'label'       => 'Prénom',
                'constraints' => [new Assert\NotBlank(message: 'Le prénom du licencié est obligatoire.')],
            ])
            ->add('dateNaissance', DateType::class, [
                'label'       => 'Date de naissance',
                'widget'      => 'single_text',
                'input'       => 'datetime_immutable',
                'attr'        => ['data-start-view' => 'years'],
                'constraints' => [new Assert\NotNull(message: 'La date de naissance est obligatoire.')],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Email du licencié',
                'help'        => 'Sert à créer son propre compte sur l\'espace en ligne.',
                'constraints' => [new Assert\NotBlank(message: 'L\'email du licencié est obligatoire.')],
            ])
            ->add('saison', EntityType::class, [
                'class'        => Saison::class,
                'choice_label' => 'libelle',
                'label'        => 'Saison',
                'choice_attr'  => static fn (Saison $s) => ['data-year' => $s->getDateFin()?->format('Y')],
                'constraints'  => [new Assert\NotNull(message: 'La saison est obligatoire.')],
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
