<?php

namespace App\Form;

use App\Entity\MailSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Serveur SMTP réglé depuis l'administration. Le mot de passe (non mappé) est chiffré par le contrôleur. */
class MailSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('actif', CheckboxType::class, ['label' => 'Utiliser ce serveur pour envoyer les e-mails', 'required' => false])
            ->add('host', TextType::class, ['label' => 'Serveur SMTP', 'required' => false, 'attr' => ['placeholder' => 'smtp.exemple.fr', 'autocomplete' => 'off']])
            ->add('port', IntegerType::class, ['label' => 'Port', 'attr' => ['min' => 1, 'max' => 65535]])
            ->add('chiffrement', ChoiceType::class, ['label' => 'Chiffrement', 'choices' => array_flip(MailSettings::CHIFFREMENTS)])
            ->add('username', TextType::class, ['label' => 'Identifiant (utilisateur)', 'required' => false, 'attr' => ['autocomplete' => 'off']])
            ->add('password', PasswordType::class, [
                'label'    => 'Mot de passe',
                'mapped'   => false,
                'required' => false,
                'always_empty' => true,
                'attr'     => ['autocomplete' => 'new-password', 'placeholder' => $options['a_un_mot_de_passe'] ? '•••••••• (inchangé, saisissez pour le remplacer)' : ''],
            ])
            ->add('retirerMotDePasse', CheckboxType::class, ['label' => 'Supprimer le mot de passe enregistré', 'mapped' => false, 'required' => false])
            ->add('verifierCertificat', CheckboxType::class, ['label' => 'Vérifier le certificat du serveur', 'required' => false])
            ->add('expediteurAdresse', EmailType::class, ['label' => 'Adresse d\'expédition', 'required' => false, 'help' => 'Apparaît comme expéditeur de tous les e-mails du site. Elle doit être autorisée par votre serveur SMTP.'])
            ->add('expediteurNom', TextType::class, ['label' => 'Nom d\'expéditeur', 'required' => false, 'attr' => ['placeholder' => 'ES Coutances']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MailSettings::class, 'a_un_mot_de_passe' => false]);
    }
}
