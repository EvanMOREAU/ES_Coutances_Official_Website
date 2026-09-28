<?php

namespace App\Service\Import;

/**
 * Une ligne brute de l'export "Foot Club" (un licencié), une fois les
 * colonnes lues et normalisées (dates parsées, chaînes vides -> null).
 */
class FootClubImportRow
{
    public ?string $numeroPersonne = null;
    public ?string $numeroLicence = null;
    public ?string $nom = null;
    public ?string $prenom = null;
    public ?string $civilite = null;
    public ?\DateTimeImmutable $dateNaissance = null;
    public ?string $lieuNaissance = null;
    public ?string $sexe = null;
    public ?string $nationalite = null;
    public ?string $voieRue = null;
    public ?string $lieuDit = null;
    public ?string $codePostal = null;
    public ?string $bureauDistributeur = null;
    public ?string $codeCategorie = null;
    public ?string $sousCategorie = null;
    public ?string $typeLicence = null;
    public ?string $telephoneDomicile = null;
    public ?string $mobilePersonnel = null;
    public ?string $emailPrincipal = null;

    // Représentant légal 1
    public ?string $reprLegal1NomPrenom = null;
    public ?string $reprLegal1VoieRue = null;
    public ?string $reprLegal1LieuDit = null;
    public ?string $reprLegal1CodePostal = null;
    public ?string $reprLegal1BureauDistrib = null;
    public ?string $reprLegal1TelMobile = null;
    public ?string $reprLegal1TelDomicile = null;
    public ?string $reprLegal1Email = null;

    // Représentant légal 2 (informatif seulement)
    public ?string $reprLegal2NomPrenom = null;
    public ?string $reprLegal2TelMobile = null;
    public ?string $reprLegal2TelDomicile = null;
    public ?string $reprLegal2Email = null;

    /** Téléphone à privilégier : mobile personnel, puis domicile. */
    public function telephonePrefere(): ?string
    {
        return $this->mobilePersonnel ?: $this->telephoneDomicile;
    }

    /** Téléphone du représentant légal 1 à privilégier : mobile, puis domicile. */
    public function telephoneReprLegal1(): ?string
    {
        return $this->reprLegal1TelMobile ?: $this->reprLegal1TelDomicile;
    }

    /** Téléphone du représentant légal 2 à privilégier : mobile, puis domicile. */
    public function telephoneReprLegal2(): ?string
    {
        return $this->reprLegal2TelMobile ?: $this->reprLegal2TelDomicile;
    }

    /** Vrai si la ligne porte un représentant légal 1 exploitable (nom + une adresse). */
    public function aReprLegal1(): bool
    {
        return null !== $this->reprLegal1NomPrenom;
    }
}
