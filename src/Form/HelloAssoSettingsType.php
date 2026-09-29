<?php

namespace App\Form;

use App\Entity\HelloAssoSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Identifiants d'API HelloAsso. Le client secret (non mappé) est chiffré par le contrôleur. */
class HelloAssoSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('actif', CheckboxType::class, [
                'label'    => 'Utiliser HelloAsso pour le paiement en ligne',
                'required' => false,
            ])
            ->add('environnement', ChoiceType::class, [
                'label'   => 'Environnement',
                'choices' => array_flip(HelloAssoSettings::ENVIRONNEMENTS),
                'help'    => 'Sandbox pour les tests (aucun débit réel), Production une fois le club prêt à encaisser.',
            ])
            ->add('organisationSlug', TextType::class, [
                'label'    => "Slug de l'organisation",
                'required' => false,
                'help'     => "Identifiant de l'organisation dans les URLs HelloAsso, ex. « cylaos-ict » pour admin.helloasso-sandbox.com/cylaos-ict.",
                'attr'     => ['autocomplete' => 'off', 'placeholder' => 'mon-organisation'],
            ])
            ->add('clientId', TextType::class, [
                'label'    => 'Client ID',
                'required' => false,
                'attr'     => ['autocomplete' => 'off'],
            ])
            ->add('clientSecret', PasswordType::class, [
                'label'        => 'Client secret',
                'mapped'       => false,
                'required'     => false,
                'always_empty' => true,
                'attr'         => ['autocomplete' => 'new-password', 'placeholder' => $options['a_un_secret'] ? '•••••••• (inchangé, saisissez pour le remplacer)' : ''],
            ])
            ->add('retirerClientSecret', CheckboxType::class, [
                'label'    => 'Supprimer le client secret enregistré',
                'mapped'   => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'   => HelloAssoSettings::class,
            'a_un_secret'  => false,
        ]);
    }
}
