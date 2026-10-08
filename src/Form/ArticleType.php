<?php

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractType<mixed> */
class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'article',
                'attr'  => ['placeholder' => 'Ex. T-shirt d\'entraînement'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 5, 'placeholder' => 'Matière, coupe, couleur…'],
            ])
            ->add('prixCentimes', MoneyType::class, [
                'label'    => 'Prix (€)',
                'currency' => false, // le symbole est dans le libellé : il ne passe pas à la ligne sous le champ
                'divisor'  => 100, // saisi en euros, stocké en centimes
                'help'     => 'Prix TTC affiché dans la boutique, identique pour toutes les tailles.',
            ])
            ->add('imageFile', VichImageType::class, [
                'label'        => 'Photo',
                'required'     => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer la photo',
                'download_uri' => false,
                'image_uri'    => false,
                'help'         => 'JPG, PNG ou WebP, 5 Mo maximum.',
            ])
            ->add('categorieNom', TextType::class, [
                'label'    => 'Catégorie',
                'required' => false,
                'mapped'   => false,
                'data'     => $options['data']?->getCategorie()?->getNom(),
                'attr'     => ['list' => 'categories-datalist', 'placeholder' => 'Ex. T-shirts', 'autocomplete' => 'off', 'data-combobox' => 'true'],
                'help'     => 'Choisissez une catégorie existante ou tapez-en une nouvelle pour la créer.',
            ])
            ->add('statut', StatutChoiceType::class, [
                'help' => 'Seuls les articles « actifs » sont visibles dans la boutique. Un brouillon ou un article archivé est conservé mais masqué.',
            ])
            ->add('variantes', CollectionType::class, [
                'entry_type'     => ArticleVarianteType::class,
                'allow_add'      => true,
                'allow_delete'   => true,
                'by_reference'   => false,
                'label'          => false,
                'error_bubbling' => false,
                'constraints'    => [new Count(min: 1, minMessage: 'Ajoutez au moins une taille (ou « Unique » pour un article sans taille).')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}
