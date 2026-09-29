<?php

namespace App\Form;

use App\Entity\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Coordonnées du client et mode de règlement, à la dernière étape de la commande. */
class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label'       => 'Prénom *',
                'constraints' => [new NotBlank(message: 'Indiquez votre prénom.'), new Length(max: 100)],
                'attr'        => ['autocomplete' => 'given-name'],
            ])
            ->add('nom', TextType::class, [
                'label'       => 'Nom *',
                'constraints' => [new NotBlank(message: 'Indiquez votre nom.'), new Length(max: 100)],
                'attr'        => ['autocomplete' => 'family-name'],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Adresse e-mail *',
                'help'        => 'Nous vous écrivons ici pour confirmer la commande et vous prévenir quand elle est prête.',
                'constraints' => [new NotBlank(message: 'Indiquez votre adresse e-mail.'), new Email(message: 'Cette adresse e-mail n\'est pas valide.'), new Length(max: 180)],
                'attr'        => ['autocomplete' => 'email'],
            ])
            ->add('telephone', TelType::class, [
                'label'       => 'Téléphone',
                'required'    => false,
                'constraints' => [new Length(max: 30)],
                'attr'        => ['autocomplete' => 'tel', 'placeholder' => '06 00 00 00 00'],
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label'       => 'Mode de règlement',
                'choices'     => array_flip(Commande::MODES_PAIEMENT),
                'expanded'    => true,
                'data'        => Commande::PAIEMENT_CARTE,
                'constraints' => [new NotBlank(message: 'Choisissez un mode de règlement.'), new Choice(choices: array_keys(Commande::MODES_PAIEMENT))],
            ])
            ->add('note', TextareaType::class, [
                'label'       => 'Un message pour le club ?',
                'required'    => false,
                'constraints' => [new Length(max: 1000)],
                'attr'        => ['rows' => 3, 'placeholder' => 'Facultatif : flocage, précision sur une taille…'],
            ])
            ->add('codePromo', TextType::class, [
                'label'       => 'Code de réduction',
                'required'    => false,
                'constraints' => [new Length(max: 30)],
                'attr'        => ['placeholder' => 'Facultatif', 'autocomplete' => 'off'],
            ])
        ;
    }
}
