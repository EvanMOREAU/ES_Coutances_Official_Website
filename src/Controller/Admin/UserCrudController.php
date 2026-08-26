<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private UserRepository $userRepository
    ) {}
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return $this->userRepository->createQueryBuilder('u')
            ->addSelect("CASE 
                WHEN u.roles LIKE '%ROLE_DEV%' THEN 0 
                WHEN u.roles LIKE '%ROLE_ADMIN%' THEN 1 
                WHEN u.roles LIKE '%ROLE_EDITOR%' THEN 2 
                ELSE 3 END AS HIDDEN role_order")
            ->orderBy('role_order', 'ASC')
            ->addOrderBy('u.nom', 'ASC');
    }
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['roles' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $actions
            // Conditionne DELETE selon le voter
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(function (User $user) {
                    return $this->isGranted(UserVoter::DELETE, $user);
                });
            })
            // Conditionne EDIT selon le voter
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->displayIf(function (User $user) {
                    return $this->isGranted(UserVoter::EDIT, $user);
                });
            });
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'Email')
            ->setSortable(false);
        yield TextField::new('nom', 'Nom complet')
            ->setSortable(false);
        yield ChoiceField::new('roles', 'Rôle')
            ->setChoices([
                'Développeur'    => 'ROLE_DEV',
                'Administrateur' => 'ROLE_ADMIN',
                'Éditeur'        => 'ROLE_EDITOR',
            ])
            ->allowMultipleChoices()
            ->setSortable(false);
        yield TextField::new('password', 'Mot de passe')
            ->setFormType(PasswordType::class)
            ->onlyOnForms()
            ->setRequired($pageName === Crud::PAGE_NEW);
}
    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->hashPasswordIfChanged($em, $entity);
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        if (!$this->isGranted(UserVoter::EDIT, $entity)) {
            throw $this->createAccessDeniedException(
                'Seul un développeur peut modifier un compte développeur.'
            );
        }
        $this->hashPasswordIfChanged($em, $entity);
        parent::updateEntity($em, $entity);
    }

    public function deleteEntity(EntityManagerInterface $em, mixed $entity): void
    {
        // Double sécurité côté serveur
        if (!$this->isGranted(UserVoter::DELETE, $entity)) {
            throw $this->createAccessDeniedException(
                'Seul un développeur peut supprimer un compte développeur.'
            );
        }
        parent::deleteEntity($em, $entity);
    }

    /**
     * Ne (re)hash le mot de passe que s'il a réellement été saisi/modifié dans
     * le formulaire. Sur l'édition, laisser le champ vide ne doit pas changer
     * le mot de passe existant : sans cette vérification, on rehasherait le
     * hash déjà stocké à chaque sauvegarde (et casserait la connexion).
     */
    private function hashPasswordIfChanged(EntityManagerInterface $em, User $user): void
    {
        $originalPassword = $em->getUnitOfWork()->getOriginalEntityData($user)['password'] ?? null;

        if ($user->getPassword() && $user->getPassword() !== $originalPassword) {
            $user->setPassword(
                $this->hasher->hashPassword($user, $user->getPassword())
            );
        }
    }
}