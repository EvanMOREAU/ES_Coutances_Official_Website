<?php

namespace App\Form;

use App\Entity\Famille;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FamilleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nom', TextType::class, [
            'label' => 'Nom de la famille',
        ]);

        if ($options['is_new']) {
            $builder->add('email', EmailType::class, [
                'label'    => 'Email de référence de la famille',
                'mapped'   => false,
                'help'     => 'Un compte est créé automatiquement à cette adresse : la famille reçoit un email pour définir son mot de passe.',
            ]);
        } else {
            $builder
                ->add('email', EmailType::class, [
                    'label'        => 'Email de référence de la famille (compte de connexion)',
                    'property_path' => 'user.email',
                ])
                ->add('prenomReferent', TextType::class, [
                    'label'          => 'Prénom du parent référent',
                    'required'       => false,
                    'property_path'  => 'user.prenom',
                    'help'           => 'Affiché entre parenthèses devant le nom de famille pour distinguer deux familles homonymes.',
                ])
            ;
        }

        $builder
            ->add('adresse', TextType::class, [
                'label'    => 'Adresse',
                'required' => false,
            ])
            ->add('codePostal', TextType::class, [
                'label'    => 'Code postal',
                'required' => false,
            ])
            ->add('ville', TextType::class, [
                'label'    => 'Ville',
                'required' => false,
            ])
            ->add('telephone', TextType::class, [
                'label'    => 'Téléphone',
                'required' => false,
            ])
            ->add('statut', StatutChoiceType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Famille::class,
            'is_new'     => false,
        ]);
    }
}
