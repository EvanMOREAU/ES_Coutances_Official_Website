<?php

namespace App\Controller\Admin;

use App\Entity\SlideCarousel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class SlideCarouselCrudController extends AbstractCrudController
{
    public function configureActions(Actions $actions): Actions
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        return $actions;
    }
    
    public static function getEntityFqcn(): string
    {
        return SlideCarousel::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Slide')
            ->setEntityLabelInPlural('Slides du carousel')
            ->setDefaultSort(['ordre' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('titre', 'Titre');
        yield TextField::new('titreMot', 'Mot mis en rouge')->setRequired(false);
        yield TextField::new('sousTitre', 'Sous-titre')->setRequired(false);
        yield Field::new('imageFile', 'Image')
            ->setFormType(VichImageType::class)
            ->onlyOnForms();
        yield ImageField::new('imageName', 'Aperçu')
            ->setBasePath('/uploads/slides')
            ->onlyOnIndex();
        yield IntegerField::new('ordre', 'Ordre');
        yield BooleanField::new('actif', 'Actif');
    }
}