<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Socle des paramètres personnels du compte connecté : les mêmes écrans servent l'administration
 * (/admin/parametres) et l'espace « Mon compte » (/mon-compte/parametres), avec deux séries de routes
 * et deux habillages.
 */
abstract class AbstractAccountController extends AbstractController
{
    /** Les mêmes réglages servent l'administration et l'espace « Mon compte » : deux séries de routes, deux habillages. */
    protected function zone(Request $request): string
    {
        return str_starts_with((string) $request->attributes->get('_route'), 'portail_') ? 'portail' : 'admin';
    }

    protected function to(Request $request, string $page): Response
    {
        return $this->redirectToRoute($this->zone($request).'_parametres_'.$page);
    }

    protected function tpl(Request $request, string $page): string
    {
        return $this->zone($request).'/parametres/'.$page.'.html.twig';
    }
}
