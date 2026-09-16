<?php

namespace App\Controller\Admin;

use App\Entity\PageContenu;
use App\Entity\RejoindreCard;
use App\Repository\PageContenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\Form\Type\VichImageType;

class RejoindreCardCrudController extends AbstractCrudController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private PageContenuRepository $pageContenuRepo,
    ) {}

    public static function getEntityFqcn(): string
    {
        return RejoindreCard::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Carte "Nous rejoindre"')
            ->setEntityLabelInPlural('Nous rejoindre')
            ->setDefaultSort(['id' => 'DESC']);
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
        yield TextField::new('titre', 'Titre');
        yield TextareaField::new('description', 'Description');
        yield Field::new('imageFile', 'Image')
            ->setFormType(VichImageType::class)
            ->onlyOnForms();
        yield ImageField::new('imageName', 'Image')
            ->setBasePath('/uploads/rejoindre')
            ->onlyOnIndex();
        yield TextField::new('boutonTexte', 'Texte du bouton')->setRequired(false);
        yield TextField::new('boutonUrl', 'Lien externe (URL absolue)')
            ->setRequired(false)
            ->setHelp('À remplir uniquement pour un lien vers un site externe, ex : https://... Le bouton ouvrira ce site dans un nouvel onglet. Laisser vide pour utiliser une page interne (voir ci-dessous).');

        yield FormField::addFieldset('Page de détail interne (contenu texte + images)')
            ->setHelp('Ignoré si un lien externe est renseigné ci-dessus.')
            ->onlyOnForms();

        yield AssociationField::new('pageDetail', 'Page existante à réutiliser')
            ->setFormTypeOption('choice_label', 'titre')
            ->setRequired(false)
            ->setHelp('Optionnel : ne remplir que si vous voulez pointer vers une page déjà créée ailleurs.');

        yield TextField::new('nouvellePageTitre', 'Ou : créer une nouvelle page')
            ->setRequired(false)
            ->onlyOnForms()
            ->setHelp(
                'Indiquez ici le titre d\'une page à créer : elle sera générée et liée automatiquement à l\'enregistrement de cette carte, '
                . 'sans avoir besoin de passer par le menu "Pages". Vous pourrez ensuite y écrire le texte et y insérer des images '
                . 'directement depuis le menu "Pages". Sans effet si "Page existante" ci-dessus est renseigné.'
            );

        yield BooleanField::new('actif', 'Actif')
            ->setHelp('Sur le site, les cartes actives s\'affichent de la plus récemment créée (en premier) à la plus ancienne.');
    }

    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->handleNouvellePage($em, $entity);
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->handleNouvellePage($em, $entity);
        parent::updateEntity($em, $entity);
    }

    private function handleNouvellePage(EntityManagerInterface $em, RejoindreCard $entity): void
    {
        $titre = trim((string) $entity->getNouvellePageTitre());

        if ('' === $titre || null !== $entity->getPageDetail()) {
            return;
        }

        $slug = (new AsciiSlugger())->slug($titre)->lower()->toString();
        $baseSlug = $slug;
        $i = 2;
        while ($this->pageContenuRepo->findOneBy(['slug' => $slug])) {
            $slug = $baseSlug . '-' . $i++;
        }

        $page = new PageContenu();
        $page->setTitre($titre);
        $page->setSlug($slug);
        $page->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($page);

        $entity->setPageDetail($page);
        $this->addFlash('success', sprintf(
            'La page "%s" a été créée et liée à cette carte. Ouvrez-la depuis le menu "Pages" pour rédiger son contenu.',
            $titre
        ));
    }
}
