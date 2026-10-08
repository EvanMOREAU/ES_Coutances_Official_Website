<?php

namespace App\Form;

use App\Entity\CodePromo;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

/** @extends AbstractType<mixed> */
class CodePromoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label'       => 'Code',
                'help'        => 'Saisi par le client au moment du paiement (insensible à la casse) : débloque une étape pour indiquer une adresse de livraison.',
                'constraints' => [
                    new NotBlank(message: 'Indiquez un code.'),
                    new Length(max: 30),
                    new Regex(pattern: '/^[A-Za-z0-9-]+$/', message: 'Uniquement lettres, chiffres et tirets.'),
                ],
            ])
            ->add('description', TextType::class, [
                'label'       => 'Description (usage interne)',
                'required'    => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('actif', CheckboxType::class, [
                'label'    => 'Bon actif',
                'required' => false,
            ])
            ->add('dateDebut', DateType::class, [
                'label'    => 'Valable à partir du',
                'required' => false,
                'widget'   => 'single_text',
            ])
            ->add('dateFin', DateType::class, [
                'label'    => "Valable jusqu'au",
                'required' => false,
                'widget'   => 'single_text',
            ])
            ->add('usageMax', IntegerType::class, [
                'label'       => "Nombre d'utilisations maximum",
                'required'    => false,
                'help'        => 'Laisser vide pour un nombre illimité.',
                'constraints' => [new PositiveOrZero(message: 'Le nombre d\'utilisations ne peut pas être négatif.')],
            ])
            ->add('utilisateur', EntityType::class, [
                'class'        => User::class,
                'label'        => 'Réservé à',
                'required'     => false,
                'placeholder'  => 'N\'importe qui (nécessite une validation avant utilisation)',
                'choice_label' => static fn (User $u) => sprintf('%s (%s)', $u->getNomComplet(), $u->getEmail()),
                'help'         => 'Un bon réservé à un compte précis est utilisable dès sa création, par ce compte uniquement. Laissé vide, il est ouvert à tout le monde mais doit d\'abord être validé.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CodePromo::class,
        ]);
    }
}
