<?php

namespace App\Form;

use App\Entity\ProfilAutorisation;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire des comptes "staff" (back-office) : développeur, administrateur,
 * éditeur. Les comptes famille/licencié sont créés depuis leurs propres écrans
 * (Familles / Licenciés), pas ici.
 * @extends AbstractType<mixed>
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label'    => 'Prénom',
                'required' => false,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'help'  => $options['is_new'] ? 'Un compte est créé automatiquement à cette adresse : la personne reçoit un email pour définir son mot de passe.' : null,
            ])
            ->add('profil', EntityType::class, [
                'class'        => ProfilAutorisation::class,
                'choice_label' => 'nom',
                'label'        => 'Profil d’autorisation',
                'required'     => false,
                'placeholder'  => 'Aucun profil (autorisations à la carte)',
                'help'         => 'Un profil prérempli les autorisations ci-dessous ; vous pouvez ensuite les ajuster pour cet utilisateur.',
                'choice_attr'  => static fn (ProfilAutorisation $p) => ['data-permissions' => json_encode($p->getPermissions())],
                'attr'         => ['data-permission-profile' => '1'],
            ])
            ->add('roles', ChoiceType::class, [
                'label'    => 'Rôle(s)',
                'choices'  => [
                    'Développeur'    => 'ROLE_DEV',
                    'Administrateur' => 'ROLE_ADMIN',
                    'Éditeur'        => 'ROLE_EDITOR',
                ],
                'multiple' => true,
                'attr'     => ['data-placeholder' => 'Choisir un ou plusieurs rôles'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new'     => false,
        ]);
    }
}
