<?php

namespace App\Service\Import;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Repository\FamilleRepository;
use App\Service\CategorieAge;
use App\Repository\LicencieRepository;
use App\Repository\SaisonRepository;
use App\Repository\UserRepository;
use App\Service\AccountActivationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Applique en base un aperçu d'import Foot Club déjà validé par
 * l'utilisateur (cf. FootClubImportParser::buildPreview). Crée ou met à jour
 * les Famille/Licencie/User correspondants, dans une seule transaction.
 */
class FootClubImportApplier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilleRepository $familleRepository,
        private readonly LicencieRepository $licencieRepository,
        private readonly UserRepository $userRepository,
        private readonly SaisonRepository $saisonRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AccountActivationMailer $activationMailer,
        private readonly \App\Service\SaisonCloture $saisonCloture,
    ) {
    }

    /**
     * @param array{groups: array<int, array>, summary: array} $preview
     *
     * @return array{famillesCreees: int, famillesMisesAJour: int, licenciesCrees: int, licenciesMisAJour: int}
     */
    public function apply(array $preview): array
    {
        $saison = $this->saisonRepository->findActive();
        if (!$saison) {
            throw new \RuntimeException("Aucune saison active n'est configurée : impossible de rattacher les licenciés importés.");
        }

        // Les licenciés dont la saison est terminée passent inactifs avant l'import : ceux qui figurent
        // dans le fichier (licence valide) sont ensuite réattribués à la saison en cours ci-dessous.
        $this->saisonCloture->cloturer();

        $result = ['famillesCreees' => 0, 'famillesMisesAJour' => 0, 'licenciesCrees' => 0, 'licenciesMisAJour' => 0];

        // Emails déjà pris (base + ceux qu'on s'apprête à créer dans cette transaction).
        $usedEmails = array_map('strtolower', array_column($this->userRepository->createQueryBuilder('u')
            ->select('u.email')->getQuery()->getScalarResult(), 'email'));
        $usedEmails = array_combine($usedEmails, $usedEmails) ?: [];

        $newFamilleUsersToNotify = [];

        // Chaque compte créé par l'import reçoit un mot de passe aléatoire, jamais connu de
        // personne ni utilisable : la personne doit passer par le lien "créer votre mot de passe"
        // (voir AccountActivationMailer) pour choisir le sien. Comme ce mot de passe temporaire
        // n'est ni révélé ni destiné à servir, un même hash peut être réutilisé pour tous les
        // comptes créés par cet import — recalculer un hash par compte (bcrypt/argon2 : ~100-500 ms
        // pièce) ferait dépasser plusieurs minutes sur un gros fichier, sans rien gagner en sécurité.
        $placeholderPassword = $this->hasher->hashPassword(new User(), bin2hex(random_bytes(32)));

        $this->em->wrapInTransaction(function () use ($preview, $saison, $placeholderPassword, &$usedEmails, &$newFamilleUsersToNotify, &$result) {
            foreach ($preview['groups'] as $group) {
                $famille = null;
                $isNewFamille = false;

                if (!empty($group['existingFamilleId'])) {
                    $famille = $this->familleRepository->find($group['existingFamilleId']);
                }

                if ($famille) {
                    // Famille existante : on complète les champs manquants, sans écraser ce qui a déjà été saisi manuellement.
                    $famille->setCivilite($famille->getCivilite() ?? ($group['civilite'] ?: null));
                    $famille->setAdresse($famille->getAdresse() ?? ($group['adresse'] ?: null));
                    $famille->setCodePostal($famille->getCodePostal() ?? ($group['codePostal'] ?: null));
                    $famille->setVille($famille->getVille() ?? ($group['ville'] ?: null));
                    $famille->setTelephone($famille->getTelephone() ?? ($group['telephone'] ?: null));
                    if ($famille->getUser() && !$famille->getUser()->getPrenom() && !empty($group['prenomReferent'])) {
                        $famille->getUser()->setPrenom($group['prenomReferent']);
                    }
                    $famille->setNomReprLegal2($group['nomReprLegal2'] ?: $famille->getNomReprLegal2());
                    $famille->setTelephoneReprLegal2($group['telephoneReprLegal2'] ?: $famille->getTelephoneReprLegal2());
                    $famille->setEmailReprLegal2($group['emailReprLegal2'] ?: $famille->getEmailReprLegal2());
                    ++$result['famillesMisesAJour'];
                } else {
                    $isNewFamille = true;

                    $email = $this->uniqueEmail($group['email'] ?? null, $usedEmails, 'famille.'.substr(md5($group['key']), 0, 12));

                    $familleUser = new User();
                    $familleUser->setEmail($email);
                    $familleUser->setNom($group['nom'] ?: 'Famille');
                    $familleUser->setPrenom($group['prenomReferent'] ?: null);
                    $familleUser->setRoles(['ROLE_FAMILLE']);
                    $familleUser->setPassword($placeholderPassword);
                    $this->em->persist($familleUser);

                    $famille = new Famille();
                    $famille->setNom($group['nom'] ?: 'Famille');
                    $famille->setCivilite($group['civilite'] ?: null);
                    $famille->setAdresse($group['adresse'] ?: null);
                    $famille->setCodePostal($group['codePostal'] ?: null);
                    $famille->setVille($group['ville'] ?: null);
                    $famille->setTelephone($group['telephone'] ?: null);
                    $famille->setNomReprLegal2($group['nomReprLegal2'] ?: null);
                    $famille->setTelephoneReprLegal2($group['telephoneReprLegal2'] ?: null);
                    $famille->setEmailReprLegal2($group['emailReprLegal2'] ?: null);
                    $famille->setUser($familleUser);
                    $this->em->persist($famille);

                    $newFamilleUsersToNotify[] = $familleUser;
                    ++$result['famillesCreees'];
                }

                $familleEmailForCompare = $famille->getUser() ? strtolower((string) $famille->getUser()->getEmail()) : null;

                foreach ($group['licencies'] as $row) {
                    $licencie = null;
                    if (!empty($row['existingLicencieId'])) {
                        $licencie = $this->licencieRepository->find($row['existingLicencieId']);
                    }

                    $dateNaissance = $row['dateNaissance'] ? new \DateTimeImmutable($row['dateNaissance']) : new \DateTimeImmutable('1900-01-01');

                    if ($licencie) {
                        $licencie->setNom($row['nom'] ?: $licencie->getNom());
                        $licencie->setPrenom($row['prenom'] ?: $licencie->getPrenom());
                        if ($row['dateNaissance']) {
                            $licencie->setDateNaissance($dateNaissance);
                        }
                        $licencie->setFamille($famille);
                        // Présent dans l'export = licence valide : rattaché à la saison en cours et réactivé.
                        $licencie->setSaison($saison);
                        $licencie->setStatut(Licencie::STATUT_ACTIVE);
                        $licencie->setNumeroLicence($row['numeroLicence'] ?: $licencie->getNumeroLicence());
                        $licencie->setCivilite($row['civilite'] ?: $licencie->getCivilite());
                        $licencie->setLieuNaissance($row['lieuNaissance'] ?: $licencie->getLieuNaissance());
                        $licencie->setSexe($row['sexe'] ?: $licencie->getSexe());
                        $licencie->setNationalite($row['nationalite'] ?: $licencie->getNationalite());
                        $licencie->setTypeLicence($row['typeLicence'] ?: $licencie->getTypeLicence());
                        $licencie->setCodeCategorie($row['codeCategorie'] ?: $licencie->getCodeCategorie());
                        $licencie->setTelephone($row['telephone'] ?: $licencie->getTelephone());
                        $licencie->setEmailIndividuel($row['emailIndividuel'] ?: $licencie->getEmailIndividuel());
                        ++$result['licenciesMisAJour'];
                    } else {
                        $ownEmail = $row['emailIndividuel'] ? strtolower($row['emailIndividuel']) : null;
                        $candidate = ($ownEmail && $ownEmail !== $familleEmailForCompare) ? $ownEmail : null;
                        $email = $this->uniqueEmail($candidate, $usedEmails, 'licencie.'.($row['numeroPersonne'] ?: bin2hex(random_bytes(4))));

                        // Adulte seul dans sa famille : il gère lui-même son compte (famille et licencié partagent le même compte).
                        $age = $row['dateNaissance'] ? CategorieAge::anneeSaisonFin($saison) - (int) $dateNaissance->format('Y') : 0;
                        if ($isNewFamille && 1 === count($group['licencies']) && $age >= 18) {
                            $licencieUser = $famille->getUser();
                            $licencieUser->setRoles(['ROLE_FAMILLE', 'ROLE_LICENCIE']);
                            $licencieUser->setPrenom($row['prenom'] ?: null);
                        } else {
                            $licencieUser = new User();
                            $licencieUser->setEmail($email);
                            $licencieUser->setNom($row['nom'] ?: '?');
                            $licencieUser->setPrenom($row['prenom'] ?: null);
                            $licencieUser->setRoles(['ROLE_LICENCIE']);
                            $licencieUser->setPassword($placeholderPassword);
                            $this->em->persist($licencieUser);
                        }

                        $licencie = new Licencie();
                        $licencie->setNom($row['nom'] ?: '?');
                        $licencie->setPrenom($row['prenom'] ?: '?');
                        $licencie->setDateNaissance($dateNaissance);
                        $licencie->setSaison($saison);
                        $licencie->setFamille($famille);
                        $licencie->setUser($licencieUser);
                        $licencie->setNumeroPersonne($row['numeroPersonne'] ?: null);
                        $licencie->setNumeroLicence($row['numeroLicence'] ?: null);
                        $licencie->setCivilite($row['civilite'] ?: null);
                        $licencie->setLieuNaissance($row['lieuNaissance'] ?: null);
                        $licencie->setSexe($row['sexe'] ?: null);
                        $licencie->setNationalite($row['nationalite'] ?: null);
                        $licencie->setTypeLicence($row['typeLicence'] ?: null);
                        $licencie->setCodeCategorie($row['codeCategorie'] ?: null);
                        $licencie->setTelephone($row['telephone'] ?: null);
                        $licencie->setEmailIndividuel($row['emailIndividuel'] ?: null);
                        $this->em->persist($licencie);
                        ++$result['licenciesCrees'];
                    }
                }
            }
        });

        foreach ($newFamilleUsersToNotify as $user) {
            $this->activationMailer->sendActivationEmail($user);
        }

        return $result;
    }

    /**
     * Retourne $preferred s'il est libre (et non déjà réservé dans $usedEmails), sinon une adresse
     * synthétique unique basée sur $fallbackLocalPart. Enregistre l'email choisi dans $usedEmails.
     *
     * @param array<string, string> $usedEmails
     */
    private function uniqueEmail(?string $preferred, array &$usedEmails, string $fallbackLocalPart): string
    {
        $preferred = $preferred ? strtolower(trim($preferred)) : null;
        if ($preferred && !isset($usedEmails[$preferred])) {
            $usedEmails[$preferred] = $preferred;

            return $preferred;
        }

        $email = strtolower($fallbackLocalPart).'@import.local';
        $suffix = 0;
        while (isset($usedEmails[$email])) {
            $email = strtolower($fallbackLocalPart).'-'.(++$suffix).'@import.local';
        }
        $usedEmails[$email] = $email;

        return $email;
    }
}
