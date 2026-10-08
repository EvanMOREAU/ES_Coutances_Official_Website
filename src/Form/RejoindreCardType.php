<?php

namespace App\Form;

use App\Entity\PageContenu;
use App\Entity\RejoindreCard;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractType<mixed> */
class RejoindreCardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description'])
            ->add('imageFile', VichImageType::class, ['label' => 'Image', 'required' => false])
            ->add('boutonTexte', TextType::class, ['label' => 'Texte du bouton', 'required' => false])
            ->add('boutonUrl', TextType::class, [
                'label'    => 'Lien externe (URL absolue)',
                'required' => false,
                'help'     => 'À remplir uniquement pour un lien vers un site externe, ex : https://... Laisser vide pour utiliser une page interne ci-dessous.',
            ])
            ->add('pageDetail', EntityType::class, [
                'class'        => PageContenu::class,
                'choice_label' => 'titre',
                'label'        => 'Page existante à réutiliser',
                'required'     => false,
                'help'         => 'Optionnel : ne remplir que si vous voulez pointer vers une page déjà créée ailleurs.',
            ])
            ->add('nouvellePageTitre', TextType::class, [
                'label'    => 'Ou : créer une nouvelle page',
                'required' => false,
                'mapped'   => true,
                'help'     => 'Indiquez le titre d\'une page à créer : elle sera générée et liée automatiquement à l\'enregistrement de cette carte. Sans effet si "Page existante" ci-dessus est renseigné.',
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'help'  => "Sur le site, les cartes actives s'affichent de la plus récemment créée en premier.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RejoindreCard::class,
        ]);
    }
}
