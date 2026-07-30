<?php

namespace App\Controller\Admin;

use App\Entity\Membre;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class MembreCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Membre::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Membre')
            ->setEntityLabelInPlural('Encadrement')
            ->setDefaultSort(['ordre' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom complet');
        yield TextField::new('poste', 'Intitulé du poste');
        yield TextField::new('diplome', 'Diplôme')->setRequired(false);
        yield AssociationField::new('categories', 'Catégories')
            ->setFormTypeOption('by_reference', false)
            ->autocomplete();
        yield Field::new('photoFile', 'Photo')
            ->setFormType(VichImageType::class)
            ->onlyOnForms();
        yield ImageField::new('photoName', 'Photo')
            ->setBasePath('/uploads/membres')
            ->onlyOnIndex();
        yield IntegerField::new('ordre', 'Ordre d\'affichage');
        yield BooleanField::new('actif', 'Actif');
    }
}