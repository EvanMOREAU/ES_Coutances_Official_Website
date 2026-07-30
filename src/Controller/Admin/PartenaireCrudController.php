<?php

namespace App\Controller\Admin;

use App\Entity\Partenaire;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class PartenaireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Partenaire::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Partenaire')
            ->setEntityLabelInPlural('Partenaires & Sponsors')
            ->setDefaultSort(['ordre' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom');
        yield UrlField::new('url', 'Site web')->setRequired(false);
        yield Field::new('logoFile', 'Logo')
            ->setFormType(VichImageType::class)
            ->onlyOnForms();
        yield ImageField::new('logoName', 'Logo')
            ->setBasePath('/uploads/partenaires')
            ->onlyOnIndex();
        yield IntegerField::new('ordre', 'Ordre');
        yield BooleanField::new('actif', 'Actif');
    }
}