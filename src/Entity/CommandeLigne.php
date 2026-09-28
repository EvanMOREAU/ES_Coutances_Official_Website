<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne d'une commande. Le nom, la taille et le prix sont recopiés au moment
 * de la commande : l'historique reste juste même si l'article change ou disparaît.
 */
#[ORM\Entity]
#[ORM\Table(name: 'commande_ligne')]
class CommandeLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: ArticleVariante::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ArticleVariante $variante = null;

    #[ORM\Column(length: 150)]
    private ?string $articleNom = null;

    #[ORM\Column(length: 50)]
    private ?string $varianteLibelle = null;

    #[ORM\Column]
    private int $prixCentimes = 0;

    #[ORM\Column]
    private int $quantite = 1;

    public static function depuis(ArticleVariante $variante, int $quantite): self
    {
        $ligne = new self();
        $ligne->variante        = $variante;
        $ligne->articleNom      = $variante->getArticle()->getNom();
        $ligne->varianteLibelle = $variante->getLibelle();
        $ligne->prixCentimes    = $variante->getArticle()->getPrixCentimes();
        $ligne->quantite        = $quantite;

        return $ligne;
    }

    public function getId(): ?int { return $this->id; }

    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $commande): static { $this->commande = $commande; return $this; }

    public function getVariante(): ?ArticleVariante { return $this->variante; }

    public function getArticleNom(): ?string { return $this->articleNom; }
    public function getVarianteLibelle(): ?string { return $this->varianteLibelle; }
    public function getPrixCentimes(): int { return $this->prixCentimes; }
    public function getQuantite(): int { return $this->quantite; }

    public function getTotalCentimes(): int { return $this->prixCentimes * $this->quantite; }

    /** Libellé lisible : « T-shirt — M » (la taille « Unique » est omise). */
    public function getLibelle(): string
    {
        return ArticleVariante::LIBELLE_UNIQUE === $this->varianteLibelle
            ? (string) $this->articleNom
            : sprintf('%s — %s', $this->articleNom, $this->varianteLibelle);
    }
}
