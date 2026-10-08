<?php

namespace App\Tests\Unit\Security;

use App\Security\RoutePermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RoutePermissionsTest extends TestCase
{
    #[DataProvider('conventionRoutes')]
    public function testConvention(string $route, string $expected): void
    {
        self::assertSame($expected, RoutePermissions::required($route, 'GET'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function conventionRoutes(): iterable
    {
        yield 'index' => ['admin_famille_index', 'famille.voir'];
        yield 'show' => ['admin_licencie_show', 'licencie.voir'];
        yield 'new' => ['admin_equipe_new', 'equipe.creer'];
        yield 'edit' => ['admin_saison_edit', 'saison.modifier'];
        yield 'delete' => ['admin_user_delete', 'utilisateur.supprimer'];
        yield 'préfixe le plus spécifique' => ['admin_partenaire_contrat_new', 'contrat_partenaire.creer'];
        yield 'catégories de la boutique' => ['admin_boutique_categorie_edit', 'article.modifier'];
    }

    public function testFreeRoutesNeedNoPermission(): void
    {
        self::assertNull(RoutePermissions::required('admin', 'GET'));
        self::assertNull(RoutePermissions::required('admin_parametres_profil', 'POST'));
    }

    public function testUnknownRouteIsDeniedByDefault(): void
    {
        self::assertFalse(RoutePermissions::required('admin_route_oubliee', 'GET'));
        self::assertSame('famille.?', RoutePermissions::required('admin_famille_bizarre', 'GET'));
    }

    public function testExplicitExceptionsWinOverConvention(): void
    {
        self::assertSame('licencie.renvoyer_acces', RoutePermissions::required('admin_licencie_resend', 'POST'));
        self::assertSame('adhesion.suivi', RoutePermissions::required('admin_adhesion_suivi', 'POST'));
        self::assertSame('journal.exporter', RoutePermissions::required('admin_journal_export', 'GET'));
    }

    #[DataProvider('readWriteRoutes')]
    public function testReadWriteSplitFollowsHttpMethod(string $route, string $read, string $write): void
    {
        self::assertSame($read, RoutePermissions::required($route, 'GET'));
        self::assertSame($read, RoutePermissions::required($route, 'HEAD'));
        self::assertSame($write, RoutePermissions::required($route, 'POST'));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function readWriteRoutes(): iterable
    {
        yield 'mail' => ['admin_mail_settings', 'mail.voir', 'mail.modifier'];
        yield 'helloasso' => ['admin_helloasso_settings', 'helloasso.voir', 'helloasso.modifier'];
        yield 'réglages' => ['admin_reglages_accueil', 'reglages.voir', 'reglages.modifier'];
        yield 'maintenance' => ['admin_boutique_maintenance', 'boutique_maintenance.voir', 'boutique_maintenance.modifier'];
    }

    public function testChatRoutesAcceptEitherMessagingPermission(): void
    {
        self::assertSame('messagerie.utiliser,messagerie.support', RoutePermissions::required('admin_chat_send', 'POST'));
    }

    public function testBulkActionDependsOnTypeAndAction(): void
    {
        $delete = Request::create('/admin/actions-groupees/famille', 'POST', ['action' => 'delete']);
        $other = Request::create('/admin/actions-groupees/famille', 'POST', ['action' => 'activer']);

        self::assertSame('famille.supprimer', RoutePermissions::required('admin_bulk_action', 'POST', ['type' => 'famille'], $delete));
        self::assertSame('famille.modifier', RoutePermissions::required('admin_bulk_action', 'POST', ['type' => 'famille'], $other));
        self::assertFalse(RoutePermissions::required('admin_bulk_action', 'POST', ['type' => 'inconnu'], $delete));
        self::assertSame('article.modifier', RoutePermissions::required('admin_toggle_actif', 'POST', ['type' => 'article']));
    }

    public function testOrderActionDependsOnAction(): void
    {
        self::assertSame('commande.annuler', RoutePermissions::required('admin_commande_action', 'POST', ['action' => 'annuler']));
        self::assertSame('commande.traiter', RoutePermissions::required('admin_commande_action', 'POST', ['action' => 'payer']));
    }
}
