<?php

namespace App\Entity;

use App\Entity\Concern\CreatedAtTrait;
use App\Entity\Concern\StatutTrait;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * Article de la boutique (ex. un maillot). Il se décline en variantes
 * (tailles), chacune avec son propre stock. Un article sans taille a une
 * unique variante « Unique ».
 */
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Article
{
    use CreatedAtTrait;
    use StatutTrait;

    /** Tailles proposées en un clic dans le formulaire. */
    public const TAILLES_VETEMENT = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
    public const TAILLES_ENFANT   = ['6 ans', '8 ans', '10 ans', '12 ans', '14 ans'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Donnez un nom à l\'article.')]
    private ?string $nom = null;

    #[ORM\Column(length: 190, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Prix en centimes d'euro (évite les erreurs d'arrondi). */
    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    private int $prixCentimes = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[Vich\UploadableField(mapping: 'article_image', fileNameProperty: 'imageName')]
    #[Assert\Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], mimeTypesMessage: 'Utilisez une image JPG, PNG ou WebP.')]
    private ?File $imageFile = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: CategorieArticle::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CategorieArticle $categorie = null;

    /** @var Collection<int, ArticleVariante> */
    #[ORM\OneToMany(targetEntity: ArticleVariante::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $variantes;

    public function __construct()
    {
        $this->variantes = new ArrayCollection();
    }

    public function __toString(): string { return $this->nom ?? ''; }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $nom): static { $this->nom = $nom; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    /** Base de slug ; l'unicité est garantie par ArticleRepository::uniqueSlug(). */
    public function slugBase(): string
    {
        return (new AsciiSlugger('fr'))->slug((string) $this->nom)->lower()->toString() ?: 'article';
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getPrixCentimes(): int { return $this->prixCentimes; }
    public function setPrixCentimes(int $prixCentimes): static { $this->prixCentimes = max(0, $prixCentimes); return $this; }

    public function getImageName(): ?string { return $this->imageName; }
    public function setImageName(?string $imageName): static { $this->imageName = $imageName; return $this; }

    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;
        if (null !== $imageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getCategorie(): ?CategorieArticle { return $this->categorie; }
    public function setCategorie(?CategorieArticle $categorie): static { $this->categorie = $categorie; return $this; }

    /** @return Collection<int, ArticleVariante> */
    public function getVariantes(): Collection { return $this->variantes; }

    public function addVariante(ArticleVariante $variante): static
    {
        if (!$this->variantes->contains($variante)) {
            $this->variantes->add($variante);
            $variante->setArticle($this);
        }

        return $this;
    }

    public function removeVariante(ArticleVariante $variante): static
    {
        $this->variantes->removeElement($variante);

        return $this;
    }

    public function getStockTotal(): int
    {
        return array_sum($this->variantes->map(static fn (ArticleVariante $v) => $v->getStock())->toArray());
    }

    public function isDisponible(): bool { return $this->getStockTotal() > 0; }

    public function isActif(): bool { return self::STATUT_ACTIVE === $this->getStatut(); }

    /** Activer / désactiver d'un clic : désactivé = archivé (conservé mais masqué de la boutique). */
    public function setActif(bool $actif): static
    {
        return $this->setStatut($actif ? self::STATUT_ACTIVE : self::STATUT_ARCHIVED);
    }

    /** Vrai s'il n'y a qu'une variante « sans taille » : le client n'a rien à choisir. */
    public function isSansDeclinaison(): bool
    {
        return 1 === $this->variantes->count() && ArticleVariante::LIBELLE_UNIQUE === $this->variantes->first()->getLibelle();
    }
}
