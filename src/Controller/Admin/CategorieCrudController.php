<?php

namespace App\Controller\Admin;

use App\Entity\Categorie;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CategorieCrudController extends AbstractCrudController
{
    public function __construct(
        private OrdreService $ordreService
    ) {}

    public static function getEntityFqcn(): string
    {
        return Categorie::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories d\'encadrement')
            ->setDefaultSort(['ordre' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom de la catégorie');

        yield IntegerField::new('ordre', 'Ordre d\'affichage')
            ->setHelp('Rempli automatiquement. Modifier uniquement si nécessaire.');

        if ($pageName === Crud::PAGE_NEW) {
            yield Field::new('nom_confirmation', 'Confirmer le nom')
                ->setFormType(TextType::class)
                ->setFormTypeOptions([
                    'mapped'   => false,
                    'required' => true,
                    'attr'     => ['placeholder' => 'Saisissez à nouveau le nom de la catégorie'],
                ])
                ->setHelp('⚠️ Saisissez le même nom que ci-dessus pour confirmer la création.');
        }
    }
    public function createEntity(string $entityFqcn): object
    {
        $entity = parent::createEntity($entityFqcn);
        $entity->setOrdre($this->ordreService->getNextOrdre(Categorie::class));
        return $entity;
    }

    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $request  = $this->getContext()->getRequest();
        $form     = $request->request->all();
        $formData = array_values($form)[0] ?? [];

        $nom             = trim($entity->getNom() ?? '');
        $nomConfirmation = trim($formData['nom_confirmation'] ?? '');

        if (strtolower($nom) !== strtolower($nomConfirmation)) {
            $this->addFlash(
                'danger',
                'Les deux noms ne correspondent pas. La catégorie n\'a pas été créée.'
            );
            return;
        }

        $this->ordreService->ensureUniqueOrdre(Categorie::class, $entity);
        parent::persistEntity($em, $entity);
        $this->addFlash('success', 'Catégorie "' . $entity->getNom() . '" créée avec succès.');
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->ordreService->ensureUniqueOrdre(Categorie::class, $entity);
        parent::updateEntity($em, $entity);
    }
}