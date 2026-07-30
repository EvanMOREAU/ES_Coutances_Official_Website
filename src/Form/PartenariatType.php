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

class PartenariatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => 'Nom complet *',
                'constraints' => [new NotBlank(), new Length(min: 2)],
                'attr'        => ['placeholder' => 'Jean Dupont'],
            ])
            ->add('entreprise', TextType::class, [
                'label'       => 'Entreprise / Organisation *',
                'constraints' => [new NotBlank()],
                'attr'        => ['placeholder' => 'Nom de votre entreprise'],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Adresse e-mail *',
                'constraints' => [new NotBlank(), new Email()],
                'attr'        => ['placeholder' => 'jean.dupont@entreprise.fr'],
            ])
            ->add('telephone', TextType::class, [
                'label'    => 'Téléphone',
                'required' => false,
                'attr'     => ['placeholder' => '06 00 00 00 00'],
            ])
            ->add('niveau', ChoiceType::class, [
                'label'   => 'Niveau de partenariat souhaité *',
                'choices' => [
                    'Partenaire Principal' => 'principal',
                    'Partenaire Or'        => 'or',
                    'Partenaire Argent'    => 'argent',
                    'Partenaire Bronze'    => 'bronze',
                    'Je ne sais pas encore' => 'indefini',
                ],
                'constraints' => [new NotBlank()],
            ])
            ->add('message', TextareaType::class, [
                'label'       => 'Message *',
                'constraints' => [new NotBlank(), new Length(min: 10)],
                'attr'        => [
                    'placeholder' => 'Décrivez votre projet de partenariat...',
                    'rows'        => 5,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}