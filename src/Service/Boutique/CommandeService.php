<?php

namespace App\Service\Boutique;

use App\Entity\ArticleVariante;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\User;
use App\Repository\CodePromoRepository;
use App\Repository\CommandeRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cycle de vie d'une commande : création (avec réservation du stock),
 * paiement, préparation, retrait, annulation (avec remise en stock).
 *
 * Le stock est décrémenté à la commande, sous verrou, pour que deux clients
 * ne puissent pas acheter le dernier exemplaire en même temps.
 */
class CommandeService
{
    public const ACTIONS = ['payer', 'preparer', 'retirer', 'annuler'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommandeRepository $commandes,
        private readonly CodePromoRepository $codesPromo,
    ) {
    }

    /**
     * @param array<int, int> $quantites id de variante => quantité
     * @param array{
     *     prenom: string, nom: string, email: string, telephone: ?string, note: ?string, modePaiement: string,
     *     codePromo?: ?string,
     *     livraisonAdresse?: ?string, livraisonComplement?: ?string, livraisonCodePostal?: ?string,
     *     livraisonVille?: ?string, livraisonTelephone?: ?string, livraisonInstructions?: ?string,
     * } $client
     *
     * @throws StockInsuffisantException
     * @throws PromoCodeException
     */
    public function passer(array $quantites, array $client, ?User $user): Commande
    {
        if ([] === $quantites) {
            throw new StockInsuffisantException('Votre panier est vide.');
        }
        ksort($quantites); // ordre de verrouillage constant : évite les blocages croisés

        return $this->em->wrapInTransaction(function () use ($quantites, $client, $user): Commande {
            $commande = (new Commande())
                ->setReference($this->commandes->nouvelleReference())
                ->setPrenom($client['prenom'])
                ->setNom($client['nom'])
                ->setEmail($client['email'])
                ->setTelephone($client['telephone'] ?: null)
                ->setNote($client['note'] ?: null)
                ->setModePaiement($client['modePaiement'])
                ->setUser($user);

            foreach ($quantites as $id => $quantite) {
                $variante = $this->em->find(ArticleVariante::class, $id);
                if ($variante) {
                    $this->em->refresh($variante, LockMode::PESSIMISTIC_WRITE); // stock à jour, ligne verrouillée
                }

                $article = $variante?->getArticle();
                if (!$variante || !$article->isActif()) {
                    throw new StockInsuffisantException('Un article de votre panier n\'est plus disponible.');
                }
                if ($variante->getStock() < $quantite) {
                    $nom = $article->isSansDeclinaison() ? $article->getNom() : sprintf('%s (%s)', $article->getNom(), $variante->getLibelle());
                    throw new StockInsuffisantException(
                        $variante->getStock() > 0
                            ? sprintf('Il ne reste que %d exemplaire%s de « %s ».', $variante->getStock(), $variante->getStock() > 1 ? 's' : '', $nom)
                            : sprintf('« %s » vient d\'être épuisé.', $nom),
                    );
                }

                $variante->setStock($variante->getStock() - $quantite);
                $commande->addLigne(CommandeLigne::depuis($variante, $quantite));
            }

            $this->appliquerCodePromo($commande, $client);

            $this->em->persist($commande);
            $this->em->flush();

            return $commande;
        });
    }

    /**
     * Valide et applique un éventuel code de réduction saisi par le client. Un code « bon de
     * livraison » exige en plus une adresse complète, sans quoi la commande est refusée.
     *
     * @param array<string, mixed> $client
     *
     * @throws PromoCodeException
     */
    private function appliquerCodePromo(Commande $commande, array $client): void
    {
        $code = trim((string) ($client['codePromo'] ?? ''));
        if ('' === $code) {
            return;
        }

        $codePromo = $this->codesPromo->findParCode($code);
        if (!$codePromo || !$codePromo->isValide()) {
            throw new PromoCodeException('Ce code de réduction est invalide ou n\'est plus valable.');
        }

        $reductionCentimes = $codePromo->calculerReductionCentimes($commande->getSousTotalCentimes());
        $commande->appliquerReduction($codePromo, $reductionCentimes);
        $codePromo->incrementerUsage();

        if ($codePromo->isAutoriseLivraison()) {
            $adresse    = trim((string) ($client['livraisonAdresse'] ?? ''));
            $codePostal = trim((string) ($client['livraisonCodePostal'] ?? ''));
            $ville      = trim((string) ($client['livraisonVille'] ?? ''));
            if ('' === $adresse || '' === $codePostal || '' === $ville) {
                throw new PromoCodeException('Ce code nécessite une adresse de livraison complète (adresse, code postal, ville).');
            }

            $complement    = trim((string) ($client['livraisonComplement'] ?? ''));
            $telephone     = trim((string) ($client['livraisonTelephone'] ?? ''));
            $instructions  = trim((string) ($client['livraisonInstructions'] ?? ''));
            $commande->setLivraison($adresse, '' !== $complement ? $complement : null, $codePostal, $ville, '' !== $telephone ? $telephone : null, '' !== $instructions ? $instructions : null);
        }
    }

    /**
     * Fait avancer la commande : payer, preparer, retirer ou annuler.
     *
     * @throws \DomainException si l'action n'est pas possible dans l'état actuel
     */
    public function appliquer(Commande $commande, string $action): void
    {
        match ($action) {
            'payer'    => $this->payer($commande),
            'preparer' => $this->preparer($commande),
            'retirer'  => $this->retirer($commande),
            'annuler'  => $this->annuler($commande),
            default    => throw new \DomainException('Action inconnue.'),
        };
        $this->em->flush();
    }

    /** Enregistre l'encaissement (carte confirmée, espèces ou chèque reçus au club). */
    public function payer(Commande $commande): void
    {
        if (Commande::STATUT_ANNULEE === $commande->getStatut()) {
            throw new \DomainException('Une commande annulée ne peut pas être payée.');
        }
        $commande->marquerPayee();
    }

    private function preparer(Commande $commande): void
    {
        if (Commande::STATUT_NOUVELLE !== $commande->getStatut()) {
            throw new \DomainException('Seule une commande à préparer peut être marquée comme prête.');
        }
        $commande->setStatut(Commande::STATUT_PRETE);
    }

    /** Remise au client : le règlement est encaissé à ce moment-là s'il ne l'était pas déjà. */
    private function retirer(Commande $commande): void
    {
        if (!$commande->isEnCours()) {
            throw new \DomainException('Cette commande ne peut plus être remise.');
        }
        $commande->marquerPayee();
        $commande->setStatut(Commande::STATUT_RETIREE);
    }

    private function annuler(Commande $commande): void
    {
        if (!$commande->isAnnulable()) {
            throw new \DomainException('Cette commande ne peut plus être annulée.');
        }

        $this->em->wrapInTransaction(function () use ($commande): void {
            foreach ($commande->getLignes() as $ligne) {
                $variante = $ligne->getVariante();
                if ($variante) {
                    $this->em->refresh($variante, LockMode::PESSIMISTIC_WRITE);
                    $variante->setStock($variante->getStock() + $ligne->getQuantite());
                }
            }
            $commande->setStatut(Commande::STATUT_ANNULEE);
        });
    }
}
