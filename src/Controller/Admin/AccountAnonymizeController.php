<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\Privacy\AccountDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Anonymisation d'un compte (famille, licencié, client) par le club, par exemple à la demande de la personne
 * (droit à l'effacement). Les factures sont conservées ; voir AccountDataService.
 */
class AccountAnonymizeController extends AbstractController
{
    #[Route('/admin/comptes/{id}/anonymiser', name: 'admin_compte_anonymiser', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function anonymize(Request $request, User $account, AccountDataService $data): Response
    {
        $back = $this->safeBack((string) $request->request->get('retour'));
        if (!$this->isCsrfTokenValid('anonymize-compte-'.$account->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$data->canAnonymize($account)) {
            $this->addFlash('error', 'Ce compte ne peut pas être anonymisé (compte de l\'équipe ou déjà anonymisé).');

            return $this->redirect($back);
        }

        $data->anonymize($account);
        $this->addFlash('success', 'Compte anonymisé : les données personnelles sont effacées, les factures sont conservées.');

        return $this->redirect($back);
    }

    /** Retour limité aux pages de l'administration (jamais une adresse extérieure). */
    private function safeBack(string $target): string
    {
        return str_starts_with($target, '/admin') && !str_starts_with($target, '//') ? $target : $this->generateUrl('admin_famille_index');
    }
}
