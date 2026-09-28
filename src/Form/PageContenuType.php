<?php

namespace App\Form;

use App\Entity\PageContenu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class PageContenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre de la page'])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'help'  => 'Utilisé dans l\'URL de la page : /page/{slug}. Ne pas utiliser "histoire" ou "infrastructure" (déjà réservés).',
            ])
            ->add('imageFile', VichImageType::class, ['label' => "Image d'en-tête", 'required' => false])
            ->add('chapo', TextareaType::class, [
                'label'    => 'Chapô',
                'required' => false,
                'help'     => "Court texte d'introduction affiché au-dessus du contenu détaillé.",
            ])
            ->add('contenu', TextareaType::class, [
                'label'    => 'Contenu détaillé',
                'required' => false,
                'help'     => 'Éditeur de texte riche : mise en forme (titres, gras, listes...), et glissez-déposez ou collez directement une image à l\'endroit voulu dans le texte pour l\'insérer.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PageContenu::class,
        ]);
    }
}
