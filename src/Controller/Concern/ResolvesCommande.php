<?php

namespace App\Controller\Concern;

use App\Entity\Commande;
use App\Repository\CommandeRepository;

/** Accès public à une commande par sa référence et son jeton secret (suivi, facture, paiement). */
trait ResolvesCommande
{
    private function findCommande(CommandeRepository $commandes, string $reference, string $token): Commande
    {
        $commande = $commandes->findOneBy(['reference' => $reference]);
        // Le jeton est comparé en temps constant : la référence seule ne suffit pas à consulter une commande.
        if (!$commande || !hash_equals((string) $commande->getToken(), $token)) {
            throw $this->createNotFoundException();
        }

        return $commande;
    }

    /** @return array{reference: string, token: string} */
    private function routeParams(Commande $commande): array
    {
        return ['reference' => (string) $commande->getReference(), 'token' => (string) $commande->getToken()];
    }
}
