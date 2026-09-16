<?php

namespace App\Controller\Admin;

use App\Entity\PageContenu;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class PageContenuCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PageContenu::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Page')
            ->setEntityLabelInPlural('Contenu de page');
    }

    public function configureActions(Actions $actions): Actions
    {
        // Les pages "histoire" et "infrastructure" sont liées à des routes fixes : ne pas les supprimer.
        // La création de nouvelles pages sert notamment aux pages de détail "Nous rejoindre".
        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('titre', 'Titre de la page');
        yield SlugField::new('slug')
            ->setTargetFieldName('titre')
            ->hideOnIndex()
            ->setHelp('Utilisé dans l\'URL de la page : /page/{slug}. Ne pas utiliser "histoire" ou "infrastructure" (déjà réservés).');
        yield Field::new('imageFile', 'Image d\'en-tête')
            ->setFormType(VichImageType::class)
            ->onlyOnForms();
        yield ImageField::new('imageName', 'Image')
            ->setBasePath('/uploads/pages')
            ->onlyOnIndex();
        yield TextareaField::new('chapo', 'Chapô')
            ->hideOnIndex()
            ->setHelp('Court texte d\'introduction affiché au-dessus du contenu détaillé.')
            ->setNumOfRows(3);
        yield TextEditorField::new('contenu', 'Contenu détaillé')
            ->hideOnIndex()
            ->setNumOfRows(12)
            ->setRequired(false)
            ->addJsFiles('js/admin-trix-upload.js')
            ->setHelp('Éditeur de texte riche : mise en forme (titres, gras, listes...), et glissez-déposez ou collez directement une image à l\'endroit voulu dans le texte pour l\'insérer.');
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm();
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $entity->setUpdatedAt(new \DateTimeImmutable());
        parent::updateEntity($em, $entity);
    }

    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $entity->setUpdatedAt(new \DateTimeImmutable());
        parent::persistEntity($em, $entity);
    }
}
