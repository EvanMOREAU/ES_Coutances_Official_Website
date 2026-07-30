<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => 'Nom complet *',
                'constraints' => [new NotBlank(), new Length(min: 2)],
                'attr'        => ['placeholder' => 'Jean Dupont'],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Adresse e-mail *',
                'constraints' => [new NotBlank(), new Email()],
                'attr'        => ['placeholder' => 'jean.dupont@exemple.fr'],
            ])
            ->add('telephone', TextType::class, [
                'label'    => 'Téléphone',
                'required' => false,
                'attr'     => ['placeholder' => '06 00 00 00 00'],
            ])
            ->add('type', ChoiceType::class, [
                'label'   => 'Type de demande *',
                'constraints' => [new NotBlank()],
                'choices' => [
                    'Renseignement général'     => 'renseignement',
                    'Inscription / Licence'     => 'inscription',
                    'Partenariat'               => 'partenariat',
                    'Presse / Médias'           => 'presse',
                    'Recrutement / Bénévolat'   => 'recrutement',
                    'Autre'                     => 'autre',
                ],
                'placeholder' => 'Choisissez un type de demande',
            ])
            ->add('sujet', TextType::class, [
                'label'       => 'Sujet *',
                'constraints' => [new NotBlank(), new Length(min: 3)],
                'attr'        => ['placeholder' => 'Objet de votre message'],
            ])
            ->add('message', TextareaType::class, [
                'label'       => 'Message *',
                'constraints' => [new NotBlank(), new Length(min: 10)],
                'attr'        => [
                    'placeholder' => 'Votre message...',
                    'rows'        => 6,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}