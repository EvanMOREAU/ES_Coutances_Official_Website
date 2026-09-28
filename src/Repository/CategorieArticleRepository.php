<?php

namespace App\Repository;

use App\Entity\CategorieArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CategorieArticle> */
class CategorieArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorieArticle::class);
    }

    /** Slug unique à partir du nom de la catégorie (suffixe -2, -3… en cas de doublon). */
    public function uniqueSlug(CategorieArticle $categorie): string
    {
        $base = $categorie->slugBase();
        $slug = $base;
        $i    = 2;
        while (($existing = $this->findOneBy(['slug' => $slug])) && $existing !== $categorie) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Retrouve une catégorie par son nom, au caractère près une fois normalisé
     * (casse, accents, espaces ignorés — via le même slug que uniqueSlug()), ou
     * en crée une nouvelle si aucune ne correspond. Retourne null si $nom est vide.
     */
    public function findOrCreateByName(?string $nom): ?CategorieArticle
    {
        $nom = trim((string) $nom);
        if ('' === $nom) {
            return null;
        }

        $categorie = new CategorieArticle();
        $categorie->setNom($nom);
        $slug = $categorie->slugBase();

        $existing = $this->findOneBy(['slug' => $slug]);
        if ($existing) {
            return $existing;
        }

        $categorie->setSlug($this->uniqueSlug($categorie));
        $this->getEntityManager()->persist($categorie);

        return $categorie;
    }
}
