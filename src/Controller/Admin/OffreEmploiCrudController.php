<?php

namespace App\Controller\Admin;

use App\Entity\OffreEmploi;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class OffreEmploiCrudController extends AbstractCrudController
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
        return OffreEmploi::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Offre d\'emploi')
            ->setEntityLabelInPlural('Offres d\'emploi');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('titre', 'Titre');
        yield ChoiceField::new('type', 'Type')
            ->setChoices([
                'Alternance'      => 'alternance',
                'Service civique' => 'service-civique',
                'BPJEPS'          => 'bpjeps',
                'CDI'             => 'cdi',
                'CDD'             => 'cdd',
            ]);
        yield TextEditorField::new('description', 'Description')->hideOnIndex();
        yield Field::new('imageFile', 'Image')
            ->setFormType(VichImageType::class)
            ->onlyOnForms();
        yield ImageField::new('imageName', 'Image')
            ->setBasePath('/uploads/offres')
            ->onlyOnIndex();
        yield BooleanField::new('actif', 'Actif');
    }
}