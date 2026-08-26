<?php

namespace App\Controller\Admin;

use App\Controller\Admin\MembreCrudController;
use App\Controller\Admin\OffreEmploiCrudController;
use App\Controller\Admin\PageContenuCrudController;
use App\Controller\Admin\SlideCarouselCrudController;
use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\PartenaireCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('ES Coutances — Admin')
            ->setLocales(['fr'])
            ->setFaviconPath('images/favicon.png');  // ou '/favicon.png'
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToUrl('Voir le site', 'fa fa-eye', '/');
        if ($this->isGranted('ROLE_DEV')) {
            yield MenuItem::section('Développeur');
            yield MenuItem::linkTo(CategorieCrudController::class, 'Catégories encadrement', 'fa fa-tags');
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::section('Club');
            yield MenuItem::linkTo(PartenaireCrudController::class, 'Partenaires', 'fa fa-handshake');
            yield MenuItem::linkTo(OffreEmploiCrudController::class, 'Offres d\'emploi', 'fa fa-briefcase');
            yield MenuItem::linkTo(SlideCarouselCrudController::class, 'Carousel', 'fa fa-sliders');
            yield MenuItem::linkTo(PageContenuCrudController::class, 'Pages', 'fa fa-file-lines');
            yield MenuItem::linkTo(MembreCrudController::class, 'Encadrement', 'fa fa-people-group');
            yield MenuItem::linkToRoute('Chiffres clés', 'fa fa-chart-simple', 'admin_chiffres_cles');

            yield MenuItem::section('Administration');
            yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');
        }

        yield MenuItem::section('Mon compte');
        yield MenuItem::linkToRoute('Changer mon mot de passe', 'fa fa-key', 'app_account_password');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-right-from-bracket');
    }
}