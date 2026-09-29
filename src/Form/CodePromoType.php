<?php

namespace App\Form;

use App\Entity\CodePromo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

class CodePromoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label'       => 'Code',
                'help'        => 'Saisi par le client au moment du paiement (insensible à la casse).',
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
            ->add('type', ChoiceType::class, [
                'label'   => 'Type de réduction',
                'choices' => array_flip(CodePromo::TYPES),
            ])
            ->add('valeur', IntegerType::class, [
                'label'       => 'Valeur',
                'help'        => 'Selon le type choisi ci-dessus : un pourcentage entre 0 et 100, ou un montant fixe en centimes (ex. 500 pour 5,00 €). Un code « bon de livraison » peut avoir une valeur de 0.',
                'constraints' => [new PositiveOrZero(message: 'La valeur ne peut pas être négative.')],
            ])
            ->add('actif', CheckboxType::class, [
                'label'    => 'Code actif',
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
            ->add('autoriseLivraison', CheckboxType::class, [
                'label'    => 'Bon de livraison',
                'required' => false,
                'help'     => "Autorise le client à renseigner une adresse pour se faire livrer sa commande, au lieu de la retirer au club.",
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
