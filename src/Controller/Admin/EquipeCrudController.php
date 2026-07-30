<?php

namespace App\Controller\Admin;

use App\Entity\Equipe;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class EquipeCrudController extends AbstractCrudController
{
    
    public function configureActions(Actions $actions): Actions
    {
        if (!$this->isGranted('ROLE_DEV')) {
            throw $this->createAccessDeniedException();
        }
        return $actions;
    }
    

    public static function getEntityFqcn(): string
    {
        return Equipe::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Équipe')
            ->setEntityLabelInPlural('Équipes')
            ->setDefaultSort(['ordre' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom');
        yield ChoiceField::new('categorie', 'Catégorie')
            ->setChoices([
                'Équipe première' => 'premiere',
                'Seniors'         => 'seniors',
                'Formation'       => 'formation',
                'Académie'        => 'academie',
                'Féminines'       => 'feminines',
            ]);
        yield TextField::new('niveau', 'Niveau')->setRequired(false);
        yield UrlField::new('lienFff', 'Lien FFF')->setRequired(false);
        yield IntegerField::new('ordre', 'Ordre d\'affichage');
    }
}