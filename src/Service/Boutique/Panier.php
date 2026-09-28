<?php

namespace App\Service\Boutique;

use App\Entity\ArticleVariante;
use App\Repository\ArticleVarianteRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Panier du visiteur, conservé en session sous la forme [id de variante => quantité].
 * detail() le confronte à la base : articles retirés ou masqués, stock qui a baissé.
 */
class Panier
{
    private const SESSION_KEY = 'boutique_panier';

    /** Quantité maximale d'une même référence dans un panier. */
    public const QUANTITE_MAX = 10;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ArticleVarianteRepository $variantes,
    ) {
    }

    /** Nombre total d'articles (pastille du menu). */
    public function count(): int
    {
        // Le menu de chaque page du site appelle ceci : sans session existante, on n'en démarre pas
        // (cela rendrait les pages publiques « privées » pour le cache HTTP).
        $request = $this->requestStack->getMainRequest();
        if (!$request?->hasSession() || (!$request->hasPreviousSession() && !$request->getSession()->isStarted())) {
            return 0;
        }

        return array_sum($this->raw());
    }

    /** Ajoute des exemplaires ; renvoie la quantité réellement présente dans le panier. */
    public function add(ArticleVariante $variante, int $quantite): int
    {
        $items = $this->raw();
        $id    = (int) $variante->getId();
        $items[$id] = $this->clamp(($items[$id] ?? 0) + $quantite, $variante);
        $this->save($items);

        return $items[$id];
    }

    public function set(ArticleVariante $variante, int $quantite): void
    {
        $items = $this->raw();
        $id    = (int) $variante->getId();
        if ($quantite <= 0) {
            unset($items[$id]);
        } else {
            $items[$id] = $this->clamp($quantite, $variante);
        }
        $this->save($items);
    }

    public function remove(int $varianteId): void
    {
        $items = $this->raw();
        unset($items[$varianteId]);
        $this->save($items);
    }

    public function clear(): void
    {
        $this->save([]);
    }

    /**
     * Contenu du panier prêt à afficher. Les lignes devenues invalides sont
     * retirées ou ajustées, avec un message pour le client.
     *
     * @return array{lignes: list<array{variante: ArticleVariante, quantite: int, total: int}>, total: int, messages: list<string>}
     */
    public function detail(): array
    {
        $items    = $this->raw();
        $lignes   = [];
        $messages = [];
        $total    = 0;

        foreach ($this->variantes->findBy(['id' => array_keys($items)]) as $variante) {
            $article  = $variante->getArticle();
            $demande  = $items[$variante->getId()];
            $nom      = $article->isSansDeclinaison() ? $article->getNom() : sprintf('%s (%s)', $article->getNom(), $variante->getLibelle());

            if (!$article->isActif() || $variante->getStock() <= 0) {
                $messages[] = sprintf('« %s » n\'est plus disponible : il a été retiré de votre panier.', $nom);
                unset($items[$variante->getId()]);
                continue;
            }

            $quantite = $this->clamp($demande, $variante);
            if ($quantite < $demande) {
                $messages[] = sprintf('Il ne reste que %d exemplaire%s de « %s » : la quantité a été ajustée.', $quantite, $quantite > 1 ? 's' : '', $nom);
                $items[$variante->getId()] = $quantite;
            }

            $ligne    = $quantite * $article->getPrixCentimes();
            $total   += $ligne;
            $lignes[] = ['variante' => $variante, 'quantite' => $quantite, 'total' => $ligne];
        }

        // Variantes supprimées depuis : on les oublie.
        $items = array_intersect_key($items, array_flip(array_map(static fn (array $l) => $l['variante']->getId(), $lignes)));
        if ($items !== $this->raw()) {
            $this->save($items);
        }

        usort($lignes, static fn (array $a, array $b) => $a['variante']->getId() <=> $b['variante']->getId());

        return ['lignes' => $lignes, 'total' => $total, 'messages' => $messages];
    }

    private function clamp(int $quantite, ArticleVariante $variante): int
    {
        return max(0, min($quantite, $variante->getStock(), self::QUANTITE_MAX));
    }

    /** @return array<int, int> */
    private function raw(): array
    {
        $session = $this->requestStack->getSession();

        return $session->get(self::SESSION_KEY, []);
    }

    /** @param array<int, int> $items */
    private function save(array $items): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $items);
    }
}
