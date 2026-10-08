<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Adresse de livraison, demandée uniquement quand le code de réduction saisi
 * l'autorise : étape intercalée entre les coordonnées et la confirmation.
 * @extends AbstractType<mixed>
 */
class LivraisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('livraisonAdresse', TextType::class, [
                'label'       => 'Adresse *',
                'constraints' => [new NotBlank(message: 'Indiquez l\'adresse de livraison.'), new Length(max: 255)],
            ])
            ->add('livraisonComplement', TextType::class, [
                'label'       => 'Complément d\'adresse',
                'required'    => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('livraisonCodePostal', TextType::class, [
                'label'       => 'Code postal *',
                'constraints' => [new NotBlank(message: 'Indiquez le code postal.'), new Length(max: 10)],
            ])
            ->add('livraisonVille', TextType::class, [
                'label'       => 'Ville *',
                'constraints' => [new NotBlank(message: 'Indiquez la ville.'), new Length(max: 100)],
            ])
            ->add('livraisonTelephone', TelType::class, [
                'label'       => 'Téléphone du destinataire',
                'required'    => false,
                'constraints' => [new Length(max: 30)],
                'attr'        => ['autocomplete' => 'tel'],
            ])
            ->add('livraisonInstructions', TextareaType::class, [
                'label'       => 'Instructions de livraison',
                'required'    => false,
                'constraints' => [new Length(max: 1000)],
                'attr'        => ['rows' => 2, 'placeholder' => 'Facultatif : digicode, horaires…'],
            ])
        ;
    }
}
