<?php

namespace App\Form;

use App\Entity\ArticleVariante;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Une taille (ou déclinaison) d'un article et son stock.
 * @extends AbstractType<mixed>
 */
class ArticleVarianteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Taille',
                'attr'  => ['placeholder' => 'M', 'maxlength' => 50],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'attr'  => ['min' => 0, 'inputmode' => 'numeric'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ArticleVariante::class]);
    }
}
