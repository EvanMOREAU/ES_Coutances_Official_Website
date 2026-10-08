<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Création libre d'un compte boutique (voir RegistrationController) : pas de famille, pas de licencié.
 * @extends AbstractType<mixed>
 */
class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label'       => 'Prénom',
                'required'    => false,
                'constraints' => [new Length(max: 100)],
                'attr'        => ['autocomplete' => 'given-name'],
            ])
            ->add('nom', TextType::class, [
                'label'       => 'Nom',
                'constraints' => [new NotBlank(message: 'Indiquez votre nom.'), new Length(max: 100)],
                'attr'        => ['autocomplete' => 'family-name'],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Adresse e-mail',
                'constraints' => [new NotBlank(message: 'Indiquez votre adresse e-mail.'), new Email(message: 'Cette adresse e-mail n\'est pas valide.'), new Length(max: 180)],
                'attr'        => ['autocomplete' => 'email'],
            ])
            ->add('consent', CheckboxType::class, [
                'mapped'      => false,
                'required'    => true,
                'label'       => "J'ai lu et j'accepte la politique de confidentialité et les conditions de vente.",
                'constraints' => [new IsTrue(message: 'Vous devez accepter la politique de confidentialité pour créer un compte.')],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'first_options'   => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'second_options'  => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints'     => [new NotBlank(message: 'Choisissez un mot de passe.'), new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
