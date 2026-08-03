<?php

namespace App\Controller\Admin;

use App\Controller\Admin\CategorieCrudController;
use App\Entity\Categorie;
use App\Entity\Membre;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class MembreCrudController extends AbstractCrudController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private OrdreService $ordreService
    ) {}

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
        $nouvelleCatUrl = $this->adminUrlGenerator
            ->setController(CategorieCrudController::class)
            ->setAction('new')
            ->generateUrl();

        yield TextField::new('nom', 'Nom complet');
        yield TextField::new('poste', 'Intitulé du poste');
        yield TextField::new('diplome', 'Diplôme')->setRequired(false);
        yield Field::new('categories', 'Catégories')
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class'        => Categorie::class,
                'choice_label' => 'nom',
                'multiple'     => true,
                'expanded'     => true,
                'by_reference' => false,
            ])
            ->setHelp(sprintf(
                'Catégorie manquante ? <a href="%s" target="_blank" style="color:var(--color-primary)">
                    <i class="fa fa-plus-circle"></i> Créer une nouvelle catégorie
                </a> (s\'ouvre dans un nouvel onglet)
                <br><span style="color:#ef4444;font-weight:600;">
                    ⚠️ Attention : après la création, rechargez cette page pour voir la nouvelle catégorie apparaître.
                </span>',
                $nouvelleCatUrl
            ))
            ->setHtmlAttributes(['class' => 'field-categories']);
        yield Field::new('photoFile', 'Photo')
            ->setFormType(VichImageType::class)
            ->onlyOnForms();
        yield ImageField::new('photoName', 'Photo')
            ->setBasePath('/uploads/membres')
            ->onlyOnIndex();
        yield IntegerField::new('ordre', 'Ordre d\'affichage')
            ->setHelp('Rempli automatiquement. Modifier uniquement si nécessaire.');
        yield BooleanField::new('actif', 'Actif');
    }

    public function createEntity(string $entityFqcn): object
    {
        $entity = parent::createEntity($entityFqcn);
        $entity->setOrdre($this->ordreService->getNextOrdre(Membre::class));
        return $entity;
    }

    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->ordreService->ensureUniqueOrdre(Membre::class, $entity);
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->ordreService->ensureUniqueOrdre(Membre::class, $entity);
        parent::updateEntity($em, $entity);
    }
}