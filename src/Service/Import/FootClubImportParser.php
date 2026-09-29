<?php

namespace App\Service\Import;

use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;

/**
 * Lit un export "Foot Club" (.xlsx, un licencié par ligne) et construit un
 * aperçu (familles/licenciés nouveaux, mis à jour ou inchangés) à partir des
 * données déjà en base. Le résultat est un tableau de types simples (pas
 * d'entités, pas d'objets \DateTime) afin de pouvoir être stocké tel quel en
 * session le temps que l'utilisateur confirme l'import.
 */
class FootClubImportParser
{
    /** Nom de colonne normalisé (cf. normalizeHeader) => propriété de FootClubImportRow. */
    private const COLUMN_MAP = [
        'numero personne'               => 'numeroPersonne',
        'numero licence'                => 'numeroLicence',
        'nom'                            => 'nom',
        'prenom'                         => 'prenom',
        'civilite'                       => 'civilite',
        'ne e le'                        => 'dateNaissance',
        'lieu de naissance'              => 'lieuNaissance',
        'sexe'                           => 'sexe',
        'nationalite'                    => 'nationalite',
        'bureau distributeur 1'          => 'bureauDistributeur',
        'voie rue'                       => 'voieRue',
        'lieu dit'                       => 'lieuDit',
        'code postal'                    => 'codePostal',
        'code categorie'                 => 'codeCategorie',
        'sous categorie'                 => 'sousCategorie',
        'type licence'                   => 'typeLicence',
        'telephone domicile'             => 'telephoneDomicile',
        'mobile personnel'               => 'mobilePersonnel',
        'email principal'                => 'emailPrincipal',
        'nom prenom repr legal 1'        => 'reprLegal1NomPrenom',
        'voie rue repr legal 1'          => 'reprLegal1VoieRue',
        'lieu dit repr legal 1'          => 'reprLegal1LieuDit',
        'code postal repr legal 1'       => 'reprLegal1CodePostal',
        'bureau distrib repr legal 1'    => 'reprLegal1BureauDistrib',
        'tel mobile repr legal 1'        => 'reprLegal1TelMobile',
        'tel domicile repr legal 1'      => 'reprLegal1TelDomicile',
        'email repr legal 1'             => 'reprLegal1Email',
        'nom prenom repr legal 2'        => 'reprLegal2NomPrenom',
        'tel mobile repr legal 2'        => 'reprLegal2TelMobile',
        'tel domicile repr legal 2'      => 'reprLegal2TelDomicile',
        'email repr legal 2'             => 'reprLegal2Email',
    ];

    /**
     * @return FootClubImportRow[]
     *
     * @throws \RuntimeException si le fichier est illisible ou n'a pas la forme attendue
     */
    public function parseFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (SpreadsheetReaderException $e) {
            throw new \RuntimeException('Impossible de lire le fichier Excel : '.$e->getMessage(), 0, $e);
        }

        $sheet = $spreadsheet->getSheetByName('Feuil1') ?? $spreadsheet->getActiveSheet();
        $highestRow    = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        $headerRow = $sheet->rangeToArray('A1:'.$highestColumn.'1', null, true, false)[0];
        $columnIndexByField = [];
        foreach ($headerRow as $index => $headerValue) {
            $normalized = self::normalizeHeader((string) $headerValue);
            if (isset(self::COLUMN_MAP[$normalized])) {
                $columnIndexByField[self::COLUMN_MAP[$normalized]] = $index;
            }
        }

        if (!isset($columnIndexByField['numeroPersonne'], $columnIndexByField['nom'], $columnIndexByField['prenom'])) {
            throw new \RuntimeException("Le fichier ne ressemble pas à un export Foot Club (colonnes attendues introuvables : Numéro personne, Nom, Prénom).");
        }

        $rows = [];
        for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
            $raw = $sheet->rangeToArray('A'.$rowIndex.':'.$highestColumn.$rowIndex, null, true, false)[0];

            $get = static function (string $field) use ($raw, $columnIndexByField): ?string {
                if (!isset($columnIndexByField[$field])) {
                    return null;
                }
                $value = $raw[$columnIndexByField[$field]] ?? null;
                if (null === $value) {
                    return null;
                }
                $value = trim((string) $value);

                return '' === $value ? null : $value;
            };

            $numeroPersonne = $get('numeroPersonne');
            $nom            = $get('nom');
            if (null === $numeroPersonne && null === $nom) {
                continue; // ligne vide en fin de tableau
            }

            $row = new FootClubImportRow();
            $row->numeroPersonne          = $numeroPersonne;
            $row->numeroLicence           = $get('numeroLicence');
            $row->nom                     = $nom;
            $row->prenom                  = $get('prenom');
            $row->civilite                = $get('civilite');
            $row->dateNaissance            = self::parseDate($get('dateNaissance'));
            $row->lieuNaissance            = $get('lieuNaissance');
            $row->sexe                     = $get('sexe');
            $row->nationalite              = $get('nationalite');
            $row->bureauDistributeur       = $get('bureauDistributeur');
            $row->voieRue                  = $get('voieRue');
            $row->lieuDit                  = $get('lieuDit');
            $row->codePostal               = self::padNumeric($get('codePostal'), 5);
            $row->codeCategorie            = $get('codeCategorie');
            $row->sousCategorie            = $get('sousCategorie');
            $row->typeLicence              = $get('typeLicence');
            $row->telephoneDomicile        = self::padNumeric($get('telephoneDomicile'), 10);
            $row->mobilePersonnel          = self::padNumeric($get('mobilePersonnel'), 10);
            $row->emailPrincipal           = self::normalizeEmail($get('emailPrincipal'));

            $row->reprLegal1NomPrenom      = $get('reprLegal1NomPrenom');
            $row->reprLegal1VoieRue        = $get('reprLegal1VoieRue');
            $row->reprLegal1LieuDit        = $get('reprLegal1LieuDit');
            $row->reprLegal1CodePostal     = self::padNumeric($get('reprLegal1CodePostal'), 5);
            $row->reprLegal1BureauDistrib  = $get('reprLegal1BureauDistrib');
            $row->reprLegal1TelMobile      = self::padNumeric($get('reprLegal1TelMobile'), 10);
            $row->reprLegal1TelDomicile    = self::padNumeric($get('reprLegal1TelDomicile'), 10);
            $row->reprLegal1Email          = self::normalizeEmail($get('reprLegal1Email'));

            $row->reprLegal2NomPrenom      = $get('reprLegal2NomPrenom');
            $row->reprLegal2TelMobile      = self::padNumeric($get('reprLegal2TelMobile'), 10);
            $row->reprLegal2TelDomicile    = self::padNumeric($get('reprLegal2TelDomicile'), 10);
            $row->reprLegal2Email          = self::normalizeEmail($get('reprLegal2Email'));

            $rows[] = $row;
        }

        return self::mergeDuplicateRows($rows);
    }

    /**
     * L'export contient parfois plusieurs lignes pour le même "Numéro
     * personne" : une personne peut cumuler plusieurs types de licence
     * (ex. Dirigeant + Libre, ou Arbitre + Libre), chacun sur sa propre
     * ligne. On fusionne ces lignes en une seule (les types de licence sont
     * concaténés) pour n'avoir qu'un seul Licencie par personne, cohérent
     * avec la clé unique numero_personne.
     *
     * @param FootClubImportRow[] $rows
     *
     * @return FootClubImportRow[]
     */
    private static function mergeDuplicateRows(array $rows): array
    {
        $byNumero = [];
        $noNumero = [];
        foreach ($rows as $row) {
            if (null === $row->numeroPersonne) {
                $noNumero[] = $row;
                continue;
            }
            if (!isset($byNumero[$row->numeroPersonne])) {
                $byNumero[$row->numeroPersonne] = $row;
                continue;
            }

            $existing = $byNumero[$row->numeroPersonne];
            foreach (get_object_vars($row) as $field => $value) {
                if (null !== $value && null === $existing->{$field}) {
                    $existing->{$field} = $value;
                }
            }
            if ($row->typeLicence && $existing->typeLicence && !str_contains($existing->typeLicence, $row->typeLicence)) {
                $existing->typeLicence .= ' + '.$row->typeLicence;
            }
        }

        return array_merge(array_values($byNumero), $noNumero);
    }

    /**
     * Regroupe les lignes en "familles" : même représentant légal 1 (nom +
     * adresse normalisés), ou à défaut le licencié lui-même s'il n'a pas de
     * représentant légal renseigné (cas d'un adulte, ex. arbitre).
     *
     * @param FootClubImportRow[] $rows
     *
     * @return array<string, FootClubImportRow[]> lignes groupées par clé de famille
     */
    public function groupRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$this->familyKey($row)][] = $row;
        }

        return $groups;
    }

    public function familyKey(FootClubImportRow $row): string
    {
        if ($row->aReprLegal1()) {
            return self::normalize($row->reprLegal1NomPrenom).'|'.self::normalize($row->reprLegal1VoieRue).'|'.self::normalize($row->reprLegal1CodePostal).'|'.self::normalize($row->reprLegal1LieuDit);
        }

        // Pas de représentant légal : la personne est sa propre famille (cas courant pour un adulte).
        return self::normalize($row->nom.' '.$row->prenom).'|'.self::normalize($row->voieRue).'|'.self::normalize($row->codePostal).'|'.self::normalize($row->lieuDit);
    }

    /**
     * Construit l'aperçu complet (statistiques + détail par ligne) en
     * confrontant les groupes à ce qui existe déjà en base. Le tableau
     * retourné est fait de types simples, prêt à être stocké en session.
     *
     * @param FootClubImportRow[] $rows
     */
    public function buildPreview(array $rows, FamilleRepository $familleRepository, LicencieRepository $licencieRepository): array
    {
        $groupedRows = $this->groupRows($rows);

        // Pré-charge les licenciés existants par numéro personne pour éviter une requête par ligne.
        $numerosPersonne = array_values(array_filter(array_map(static fn (FootClubImportRow $r) => $r->numeroPersonne, $rows)));
        $existingLicenciesByNumero = [];
        if ($numerosPersonne) {
            foreach ($licencieRepository->createQueryBuilder('l')
                ->andWhere('l.numeroPersonne IN (:numeros)')
                ->setParameter('numeros', $numerosPersonne)
                ->getQuery()->getResult() as $licencie) {
                $existingLicenciesByNumero[$licencie->getNumeroPersonne()] = $licencie;
            }
        }

        $existingFamilles = $familleRepository->findAll();

        $groups   = [];
        $summary  = ['famillesNouvelles' => 0, 'famillesExistantes' => 0, 'licenciesNouveaux' => 0, 'licenciesMisAJour' => 0];

        foreach ($groupedRows as $key => $groupRows) {
            $first = $groupRows[0];

            // 1) La famille est-elle déjà connue via un licencié déjà importé (numéro personne) ? On
            // recense TOUS les matches (et pas seulement le premier) pour pouvoir détecter un conflit
            // (des licenciés du même groupe rattachés jusqu'ici à des familles différentes en base).
            $matchedExistingFamilles = [];
            foreach ($groupRows as $r) {
                if ($r->numeroPersonne && isset($existingLicenciesByNumero[$r->numeroPersonne])) {
                    $famille = $existingLicenciesByNumero[$r->numeroPersonne]->getFamille();
                    if ($famille) {
                        $matchedExistingFamilles[$famille->getId()] = $famille;
                    }
                }
            }
            $existingFamille = $matchedExistingFamilles ? reset($matchedExistingFamilles) : null;

            // 2) Sinon, on tente le rapprochement par nom + adresse normalisés.
            if (!$existingFamille) {
                foreach ($existingFamilles as $famille) {
                    $familleNomKey    = self::normalize((string) $famille->getNom());
                    $familleAdresseKey = self::normalize((string) $famille->getAdresse());
                    $familleCpKey      = self::normalize((string) $famille->getCodePostal());
                    if ($familleNomKey !== '' && $familleNomKey === explode('|', $key)[0]
                        && $familleAdresseKey === explode('|', $key)[1]
                        && $familleCpKey === explode('|', $key)[2]) {
                        $existingFamille = $famille;
                        break;
                    }
                }
            }

            if ($existingFamille) {
                ++$summary['famillesExistantes'];
            } else {
                ++$summary['famillesNouvelles'];
            }

            [$nomFamille, $prenomReferent] = $first->aReprLegal1()
                ? self::splitNomPrenom($first->reprLegal1NomPrenom)
                : [$first->nom, $first->prenom];

            $groupAdresse    = $first->aReprLegal1() ? $first->reprLegal1VoieRue : $first->voieRue;
            $groupCodePostal = $first->aReprLegal1() ? $first->reprLegal1CodePostal : $first->codePostal;

            // Vérification de véracité du regroupement : plusieurs familles existantes différentes
            // matchées dans un même groupe (conflit certain), ou une adresse qui ne correspond plus à
            // celle déjà enregistrée pour la famille existante matchée (à vérifier avant de confirmer).
            $verification = null;
            if (count($matchedExistingFamilles) > 1) {
                $verification = [
                    'level'   => 'conflict',
                    'message' => sprintf(
                        "Ces licenciés étaient jusqu'ici rattachés à %d familles différentes en base : vérifiez le regroupement avant de confirmer.",
                        count($matchedExistingFamilles)
                    ),
                ];
            } elseif ($existingFamille && isset($matchedExistingFamilles[$existingFamille->getId()])) {
                $matchedAdresse = self::normalize((string) $existingFamille->getAdresse());
                $matchedCp      = self::normalize((string) $existingFamille->getCodePostal());
                $newAdresse     = self::normalize((string) $groupAdresse);
                $newCp          = self::normalize((string) $groupCodePostal);
                if ('' !== $matchedAdresse && '' !== $newAdresse && ($matchedAdresse !== $newAdresse || $matchedCp !== $newCp)) {
                    $verification = [
                        'level'   => 'risk',
                        'message' => "L'adresse du fichier diffère de celle déjà enregistrée pour cette famille : vérifiez qu'il s'agit bien de la même famille.",
                    ];
                }
            }

            $licenciesData = [];
            foreach ($groupRows as $index => $row) {
                $existingLicencie = $row->numeroPersonne ? ($existingLicenciesByNumero[$row->numeroPersonne] ?? null) : null;
                if ($existingLicencie) {
                    ++$summary['licenciesMisAJour'];
                } else {
                    ++$summary['licenciesNouveaux'];
                }

                [$rowNomFamille, $rowPrenomReferent] = $row->aReprLegal1()
                    ? self::splitNomPrenom($row->reprLegal1NomPrenom)
                    : [$row->nom, $row->prenom];

                $licenciesData[] = [
                    'status'             => $existingLicencie ? 'update' : 'new',
                    'existingLicencieId' => $existingLicencie?->getId(),
                    'rowToken'           => $row->numeroPersonne ?: md5($key.'#'.$index.'#'.$row->nom.'#'.$row->prenom),
                    'numeroPersonne'     => $row->numeroPersonne,
                    'numeroLicence'      => $row->numeroLicence,
                    'nom'                => $row->nom,
                    'prenom'             => $row->prenom,
                    'civilite'           => $row->civilite,
                    'dateNaissance'      => $row->dateNaissance?->format('Y-m-d'),
                    'lieuNaissance'      => $row->lieuNaissance,
                    'sexe'               => $row->sexe,
                    'nationalite'        => $row->nationalite,
                    'typeLicence'        => $row->typeLicence,
                    'codeCategorie'      => $row->codeCategorie,
                    'telephone'          => $row->telephonePrefere(),
                    'emailIndividuel'    => $row->emailPrincipal,
                    // Ce que cette ligne porterait comme famille si elle était détachée de son groupe
                    // (cf. FootClubImportParser::applyDetachments), utilisé par l'aperçu d'import.
                    'ownFamilleCandidate' => [
                        'nom'            => $rowNomFamille ?: ($row->nom ?: 'Famille'),
                        'prenomReferent' => $rowPrenomReferent,
                        'civilite'       => $row->aReprLegal1() ? null : $row->civilite,
                        'adresse'        => $row->aReprLegal1() ? $row->reprLegal1VoieRue : $row->voieRue,
                        'codePostal'     => $row->aReprLegal1() ? $row->reprLegal1CodePostal : $row->codePostal,
                        'ville'          => $row->aReprLegal1() ? $row->reprLegal1BureauDistrib : $row->bureauDistributeur,
                        'telephone'      => $row->aReprLegal1() ? $row->telephoneReprLegal1() : $row->telephonePrefere(),
                        'email'          => $row->aReprLegal1() ? $row->reprLegal1Email : $row->emailPrincipal,
                    ],
                ];
            }

            $groups[] = [
                'key'                 => $key,
                'status'              => $existingFamille ? 'existing' : 'new',
                'existingFamilleId'   => $existingFamille?->getId(),
                'nom'                 => $nomFamille ?: ($first->nom ?? 'Famille'),
                'prenomReferent'      => $prenomReferent,
                'civilite'            => $first->aReprLegal1() ? null : $first->civilite,
                'adresse'             => $groupAdresse,
                'codePostal'          => $groupCodePostal,
                'ville'               => $first->aReprLegal1() ? $first->reprLegal1BureauDistrib : $first->bureauDistributeur,
                'telephone'           => $first->aReprLegal1() ? $first->telephoneReprLegal1() : $first->telephonePrefere(),
                'email'               => $first->aReprLegal1() ? $first->reprLegal1Email : $first->emailPrincipal,
                'nomReprLegal2'       => $first->reprLegal2NomPrenom,
                'telephoneReprLegal2' => $first->telephoneReprLegal2(),
                'emailReprLegal2'     => $first->reprLegal2Email,
                'verification'        => $verification,
                'licencies'           => $licenciesData,
            ];
        }

        return ['groups' => $groups, 'summary' => $summary];
    }

    /**
     * Applique les détachements demandés par l'admin depuis l'aperçu d'import (case "Retirer de
     * cette famille" sur un licencié) : chaque ligne détachée quitte son groupe d'origine pour
     * former sa propre famille (à partir de ownFamilleCandidate). Si un groupe se retrouve sans
     * aucune ligne restante, il est simplement omis du résultat : aucune famille vide n'est jamais
     * créée par l'import.
     *
     * @param array{groups: array<int, array>, summary: array} $preview
     * @param list<string>                                     $detachTokens
     *
     * @return array{groups: array<int, array>, summary: array}
     */
    public function applyDetachments(array $preview, array $detachTokens): array
    {
        if ([] === $detachTokens) {
            return $preview;
        }

        $detachSet = array_flip($detachTokens);
        $newGroups = [];

        foreach ($preview['groups'] as $group) {
            $kept = [];
            foreach ($group['licencies'] as $row) {
                if (isset($detachSet[$row['rowToken']])) {
                    $candidate   = $row['ownFamilleCandidate'];
                    $newGroups[] = [
                        'key'                 => 'detach-'.$row['rowToken'],
                        'status'              => 'new',
                        'existingFamilleId'   => null,
                        'nom'                 => $candidate['nom'],
                        'prenomReferent'      => $candidate['prenomReferent'],
                        'civilite'            => $candidate['civilite'],
                        'adresse'             => $candidate['adresse'],
                        'codePostal'          => $candidate['codePostal'],
                        'ville'               => $candidate['ville'],
                        'telephone'           => $candidate['telephone'],
                        'email'               => $candidate['email'],
                        'nomReprLegal2'       => null,
                        'telephoneReprLegal2' => null,
                        'emailReprLegal2'     => null,
                        'verification'        => null,
                        'licencies'           => [$row],
                    ];
                } else {
                    $kept[] = $row;
                }
            }
            if ([] !== $kept) {
                $group['licencies'] = $kept;
                $newGroups[]        = $group;
            }
        }

        $summary = ['famillesNouvelles' => 0, 'famillesExistantes' => 0, 'licenciesNouveaux' => 0, 'licenciesMisAJour' => 0];
        foreach ($newGroups as $group) {
            ++$summary['new' === $group['status'] ? 'famillesNouvelles' : 'famillesExistantes'];
            foreach ($group['licencies'] as $row) {
                ++$summary['new' === $row['status'] ? 'licenciesNouveaux' : 'licenciesMisAJour'];
            }
        }

        return ['groups' => $newGroups, 'summary' => $summary];
    }

    private static function splitNomPrenom(?string $nomPrenom): array
    {
        $nomPrenom = trim((string) $nomPrenom);
        if ('' === $nomPrenom) {
            return [null, null];
        }

        $words = preg_split('/\s+/', $nomPrenom) ?: [$nomPrenom];
        $nomWords = [];
        while ($words && self::isUpperWord($words[0])) {
            $nomWords[] = array_shift($words);
        }

        if (!$nomWords) {
            // Rien n'est en majuscules (fichier saisi tout en minuscules) : on ne peut plus se fier à
            // la casse pour distinguer nom et prénom. On retombe sur la convention "NOM Prénom" : le
            // dernier mot est le prénom, le reste le nom. Imparfait sur les noms composés, mais
            // préférable à perdre le prénom (utile à l'affichage "(Prénom) NOM") ; l'admin peut
            // corriger via le champ "Prénom du parent référent" de la fiche famille.
            if (count($words) < 2) {
                return [$nomPrenom, null];
            }
            $prenom = array_pop($words);

            return [implode(' ', $words), $prenom];
        }

        return [implode(' ', $nomWords), $words ? implode(' ', $words) : null];
    }

    private static function isUpperWord(string $word): bool
    {
        return $word === mb_strtoupper($word, 'UTF-8') && $word !== mb_strtolower($word, 'UTF-8');
    }

    public static function normalizeHeader(string $header): string
    {
        $ascii = strtolower(self::stripAccents($header));
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? $ascii;

        return trim($ascii);
    }

    /** Normalise une chaîne pour comparaison (dédup familles) : accents retirés, majuscules, espaces réduits. */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }
        $ascii = mb_strtoupper(self::stripAccents($value), 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? $ascii);
    }

    /**
     * Retire les accents/diacritiques d'une chaîne UTF-8. On évite
     * iconv(...//TRANSLIT...) qui produit des résultats incohérents selon la
     * plateforme (ex. "é" -> "'e" sous Windows) et on passe par intl quand
     * disponible, avec un repli par table de correspondance sinon.
     */
    private static function stripAccents(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                $result = $transliterator->transliterate($value);
                if (false !== $result) {
                    return $result;
                }
            }
        }

        static $map = null;
        if (null === $map) {
            $lower = [
                'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
                'ç' => 'c',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ñ' => 'n',
                'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
                'ý' => 'y', 'ÿ' => 'y',
                'œ' => 'oe', 'æ' => 'ae',
            ];
            $map = $lower;
            foreach ($lower as $accented => $plain) {
                $map[mb_strtoupper($accented, 'UTF-8')] = mb_strtoupper($plain, 'UTF-8');
            }
        }

        return strtr($value, $map);
    }

    /**
     * Excel lit les codes postaux / numéros de téléphone comme des nombres et
     * en perd donc le zéro initial (ex. "50480" ok, mais "0645..." -> "645...").
     * On complète à gauche par des zéros quand la valeur est purement
     * numérique et plus courte que la longueur attendue.
     */
    private static function padNumeric(?string $value, int $length): ?string
    {
        if (null === $value || !ctype_digit($value)) {
            return $value;
        }

        return \strlen($value) < $length ? str_pad($value, $length, '0', \STR_PAD_LEFT) : $value;
    }

    private static function normalizeEmail(?string $email): ?string
    {
        if (null === $email) {
            return null;
        }
        $email = strtolower(trim($email));

        return '' === $email ? null : $email;
    }

    private static function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $value);

        return $date ?: null;
    }
}
