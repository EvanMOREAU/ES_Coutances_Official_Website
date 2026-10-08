<?php

namespace App\Form;

use App\Entity\CategoriePartenaire;
use App\Entity\Partenaire;
use Doctrine\ORM\EntityRepository;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractType<mixed> */
class PartenaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('url', UrlType::class, ['label' => 'Site web', 'required' => false])
            ->add('logoFile', VichImageType::class, ['label' => 'Logo', 'required' => false])
            ->add('categorie', EntityType::class, [
                'class'         => CategoriePartenaire::class,
                'label'         => 'Catégorie',
                'required'      => false,
                'placeholder'   => 'Aucune',
                'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('c')->orderBy('c.ordre', SortDirection::Ascending),
            ])
            ->add('statut', StatutChoiceType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire::class,
        ]);
    }
}
