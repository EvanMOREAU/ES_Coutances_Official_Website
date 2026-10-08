<?php

namespace App\Form;

use App\Entity\Equipe;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Saison;
use App\Service\CategorieAge;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Fiche d'un licencié. L'adresse e-mail (non mappée) est celle de son compte du
 * portail : à la création, le compte est créé et un e-mail lui est envoyé pour
 * choisir son mot de passe. Un licencié « autonome » (adulte) n'a pas de parent :
 * une famille à son nom est créée et il accède lui-même à ses factures.
 * @extends AbstractType<mixed>
 */
class LicencieType extends AbstractType
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
                'mapped'      => false,
                'label'       => 'E-mail du compte',
                'data'        => $options['email'],
                'help'        => $options['nouveau']
                    ? 'Un e-mail lui est envoyé pour créer son mot de passe.'
                    : 'Adresse de connexion du licencié. Corrigez-la ici si besoin, puis renvoyez-lui l\'accès depuis la liste.',
                'constraints' => [new NotBlank(message: 'Indiquez une adresse e-mail.'), new Email(message: 'Adresse e-mail invalide.')],
            ]);

        if ($options['nouveau']) {
            $builder->add('autonome', CheckboxType::class, [
                'mapped'   => false,
                'required' => false,
                'label'    => 'Ce licencié gère lui-même son compte (adulte, sans parent)',
                'help'     => 'Il accède à son planning et à ses factures. Une famille à son nom est créée automatiquement.',
            ]);
        }

        $builder->add('famille', EntityType::class, [
            'class'        => Famille::class,
            'choice_label' => 'nom',
            'label'        => 'Famille',
            'required'     => false,
            'placeholder'  => 'Choisir une famille',
            'help'         => 'Les mineurs dépendent d\'une famille : leur propre compte ne donne accès qu\'au planning.',
        ]);

        $builder
            ->add('saison', EntityType::class, [
                'class'        => Saison::class,
                'choice_label' => 'libelle',
                'label'        => 'Saison',
                'choice_attr'  => static fn (Saison $s) => ['data-year' => $s->getDateFin()?->format('Y')],
            ])
            ->add('decalageCategorie', ChoiceType::class, [
                'label'   => "Catégorie d'âge",
                'choices' => CategorieAge::decalageChoices(),
                'help'    => "Calculée automatiquement d'après la date de naissance ; surclassez ou sous-classez le joueur si besoin.",
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
            ->add('droitImage', ChoiceType::class, [
                'label'       => "Droit à l'image",
                'choices'     => ['Autorisé' => true, 'Refusé' => false],
                'placeholder' => 'Non renseigné (traité comme un refus)',
                'required'    => false,
                'help'        => 'Autorisation de publier des photos/vidéos du licencié (site, matchs en direct). Pour un mineur : celle de son représentant légal, recueillie par écrit.',
            ])
            ->add('autorisationParentaleAt', DateType::class, [
                'label'    => 'Autorisation parentale recueillie le',
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
                'required' => false,
                'help'     => "Licencié mineur : date à laquelle l'autorisation écrite d'inscription du représentant légal a été reçue.",
            ])
            ->add('statut', StatutChoiceType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Licencie::class,
            'nouveau'    => false,
            'email'      => null,
        ]);
    }
}
