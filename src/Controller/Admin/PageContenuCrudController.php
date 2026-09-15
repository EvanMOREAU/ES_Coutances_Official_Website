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
        return $actions
            ->disable(Action::DELETE)   // supprime le bouton Delete
            ->disable(Action::NEW);     // optionnel : empêche aussi la création de nouvelles pages
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('titre', 'Titre de la page');
        yield SlugField::new('slug')->setTargetFieldName('titre')->hideOnIndex();
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
        yield TextEditorField::new('contenu', 'Contenu détaillé')->hideOnIndex()->setNumOfRows(12);
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
