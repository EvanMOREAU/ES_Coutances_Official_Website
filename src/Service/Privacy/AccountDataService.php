<?php

namespace App\Service\Privacy;

use App\Entity\Adhesion;
use App\Entity\Commande;
use App\Entity\Conversation;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\AdhesionRepository;
use App\Repository\ConversationRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Service\FileManager\FileStorage;
use App\Service\FileManager\UserDocumentSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * RGPD : récapitulatif des données d'un compte (droit d'accès) et anonymisation (droit à l'effacement).
 *
 * L'anonymisation vide le compte, la famille, les licenciés et les commandes de leurs données personnelles
 * (nom, adresse, téléphone, e-mail, messages, documents, clés d'accès…) mais CONSERVE les pièces comptables :
 * licences (montants, règlements, aides) et commandes (références, lignes, totaux, dates). Le club garde ainsi
 * la trace de ses factures sans pouvoir identifier la personne.
 */
class AccountDataService
{
    /** Champs jamais exportés (secrets de sécurité). */
    private const HIDDEN_FIELDS = ['password', 'totpSecret', 'emailAuthCode', 'backupCodes', 'webauthnUserHandle', 'tablePreferences', 'roles', 'accesRestreint', 'emailAuthEnabled', 'permissionsAjoutees', 'permissionsRetirees', 'changelogVersionVue', 'anonymizedAt', 'updatedAt'];

    /** Intitulés lisibles des champs dont le nom technique ne se traduit pas tout seul. */
    private const LABELS = [
        'prenom' => 'Prénom', 'telephone' => 'Téléphone', 'theme' => 'Thème', 'colorScheme' => "Couleur d'accent", 'density' => 'Densité',
        'avatarName' => 'Photo de profil', 'lastSeenAt' => 'Dernière activité', 'notificationPreferences' => 'Préférences de notification',
        'createdAt' => 'Créé le', 'dateNaissance' => 'Date de naissance', 'lieuNaissance' => 'Lieu de naissance', 'codePostal' => 'Code postal',
        'montantBaseCentimes' => 'Prix de la licence (centimes)', 'reductionCentimes' => 'Réduction (centimes)', 'reductionMotif' => 'Motif de la réduction',
        'montantCentimes' => 'Montant (centimes)', 'totalCentimes' => 'Total (centimes)', 'dateEcheance' => 'Échéance', 'dateRemise' => 'Remise le',
        'recu' => 'Encaissé', 'recue' => 'Reçue', 'reference' => 'Référence', 'statut' => 'Statut', 'modePaiement' => 'Mode de paiement', 'reglement' => 'Règlement',
        'payeeLe' => 'Payée le', 'livraisonDemandee' => 'Livraison demandée', 'livraisonAdresse' => 'Adresse de livraison', 'livraisonCodePostal' => 'Code postal de livraison',
        'livraisonVille' => 'Ville de livraison', 'livraisonTelephone' => 'Téléphone de livraison', 'livraisonComplement' => "Complément d'adresse", 'livraisonInstructions' => 'Instructions de livraison',
        'nomReprLegal2' => 'Représentant légal 2', 'telephoneReprLegal2' => 'Téléphone du représentant légal 2', 'emailReprLegal2' => 'E-mail du représentant légal 2',
        'emailIndividuel' => 'E-mail individuel', 'numeroPersonne' => 'Numéro de personne', 'numeroLicence' => 'Numéro de licence', 'typeLicence' => 'Type de licence',
        'codeCategorie' => 'Catégorie', 'decalageCategorie' => 'Décalage de catégorie', 'nationalite' => 'Nationalité', 'civilite' => 'Civilité', 'actif' => 'Actif',
        'quantite' => 'Quantité', 'nomArticle' => 'Article', 'taille' => 'Taille', 'prixCentimes' => 'Prix unitaire (centimes)', 'ordre' => 'Ordre',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilleRepository $familles,
        private readonly LicencieRepository $licencies,
        private readonly AdhesionRepository $adhesions,
        private readonly ConversationRepository $conversations,
        private readonly FileStorage $storage,
        private readonly UserDocumentSpace $space,
    ) {
    }

    // -- Droit d'accès ---------------------------------------------------------

    /**
     * Toutes les données rattachées au compte, sous forme de sections lisibles.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $data = [
            'Généré le' => (new \DateTimeImmutable())->format('d/m/Y H:i'),
            'Compte'    => $this->dump($user, hide: self::HIDDEN_FIELDS) + [
                'Double authentification' => [
                    'Application' => $user->isTotpAuthenticationEnabled() ? 'activée' : 'non',
                    'Code par e-mail' => $user->isEmailAuthEnabled() ? 'activé' : 'non',
                ],
            ],
        ];

        $familleSections = [];
        $licencies       = [];
        foreach ($this->familles->findBy(['user' => $user]) as $famille) {
            $familleSections[] = $this->dump($famille);
            foreach ($famille->getLicencies() as $licencie) {
                $licencies[$licencie->getId()] = $licencie;
            }
        }
        foreach ($this->licencies->findBy(['user' => $user]) as $licencie) {
            $licencies[$licencie->getId()] = $licencie;
        }
        if ($familleSections !== []) {
            $data['Famille'] = $familleSections;
        }

        $licenciesData = [];
        foreach ($licencies as $licencie) {
            $row = $this->dump($licencie);
            $row['Licences (paiements)'] = array_map(fn (Adhesion $a) => $this->dumpAdhesion($a), $this->adhesions->forLicencie($licencie));
            $licenciesData[] = $row;
        }
        if ($licenciesData !== []) {
            $data['Licenciés'] = $licenciesData;
        }

        $commandes = [];
        foreach ($this->em->getRepository(Commande::class)->findBy(['user' => $user], ['id' => 'DESC']) as $commande) {
            $row = $this->dump($commande, hide: ['token']);
            $row['Articles'] = array_map(fn (object $l) => $this->dump($l), $commande->getLignes()->toArray());
            $commandes[] = $row;
        }
        if ($commandes !== []) {
            $data['Commandes de la boutique'] = $commandes;
        }

        $discussions = [];
        foreach ($this->conversations->findForUser($user) as $conversation) {
            $messages = $this->em->getRepository(Message::class)->findBy(['conversation' => $conversation], ['id' => 'ASC']);
            $discussions[] = [
                'Discussion' => $conversation->isSupport() ? 'Support ES Coutances' : ($conversation->getSubject() ?: 'Discussion #'.$conversation->getId()),
                'Messages'   => array_map(fn (Message $m) => [
                    'Date'    => $m->getCreatedAt()->format('d/m/Y H:i'),
                    'De'      => $m->getAuthor()?->getId() === $user->getId() ? 'Vous' : ($conversation->isSupport() && $m->getAuthor()?->isStaff() ? 'Support' : ($m->getAuthor()?->getNomComplet() ?: 'Compte supprimé')),
                    'Message' => $m->getBody(),
                ], $messages),
            ];
        }
        if ($discussions !== []) {
            $data['Messagerie'] = $discussions;
        }

        $files = [];
        try {
            $root = $this->storage->resolve($this->space->rootPath($user));
            foreach ($root->exists ? $this->storage->filesUnder($root) : [] as $entry) {
                $files[] = ['Fichier' => $entry['name'], 'Taille (octets)' => $entry['size'], 'Modifié le' => date('d/m/Y H:i', (int) $entry['mtime'])];
            }
        } catch (\Throwable) {
            // pas d'espace personnel
        }
        if ($files !== []) {
            $data['Documents hébergés'] = $files;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function dumpAdhesion(Adhesion $a): array
    {
        $row = $this->dump($a);
        $row['Règlements'] = array_map(fn (object $r) => $this->dump($r), $a->getReglements()->toArray());
        $row['Aides'] = array_map(fn (object $r) => $this->dump($r), $a->getAides()->toArray());

        return $row;
    }

    /**
     * Valeurs simples d'une entité (champs mappés), prêtes à être affichées.
     *
     * @param list<string> $hide
     *
     * @return array<string, mixed>
     */
    private function dump(object $entity, array $hide = []): array
    {
        $meta = $this->em->getClassMetadata($entity::class);
        $out  = [];
        foreach ($meta->getFieldNames() as $field) {
            if ($field === 'id' || in_array($field, $hide, true) || in_array($field, self::HIDDEN_FIELDS, true) && $entity instanceof User) {
                continue;
            }
            $value = $meta->getFieldValue($entity, $field);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[$this->label($field)] = match (true) {
                $value instanceof \DateTimeInterface => $value->format($value->format('H:i') === '00:00' ? 'd/m/Y' : 'd/m/Y H:i'),
                is_bool($value)                      => $value ? 'oui' : 'non',
                is_array($value)                     => json_encode($value, JSON_UNESCAPED_UNICODE),
                default                              => $value,
            };
        }

        return $out;
    }

    private function label(string $field): string
    {
        return self::LABELS[$field] ?? ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $field))));
    }

    // -- Droit à l'effacement --------------------------------------------------

    /** Un compte de l'équipe n'est jamais anonymisé de cette manière (on le supprime depuis « Utilisateurs »). */
    public function canAnonymize(User $user): bool
    {
        return !$user->isStaff() && !$user->isAnonymized();
    }

    /**
     * Anonymise le compte et tout ce qui s'y rattache. Les factures (licences, commandes) sont conservées.
     */
    public function anonymize(User $user): void
    {
        if (!$this->canAnonymize($user)) {
            throw new \LogicException('Ce compte ne peut pas être anonymisé.');
        }
        $userId = (int) $user->getId();

        // 1. Famille, licenciés et leurs comptes
        $accounts = [$user];
        foreach ($this->familles->findBy(['user' => $user]) as $famille) {
            foreach ($famille->getLicencies() as $licencie) {
                if ($licencie->getUser() !== null && $licencie->getUser()->getId() !== $userId) {
                    $accounts[] = $licencie->getUser();
                }
                $this->wipeLicencie($licencie);
            }
            $this->wipeFamille($famille);
        }
        foreach ($this->licencies->findBy(['user' => $user]) as $licencie) {
            $this->wipeLicencie($licencie);
        }

        // 2. Commandes : on garde le contenu comptable, pas l'identité
        foreach ($this->em->getRepository(Commande::class)->findBy(['user' => $user]) as $commande) {
            $meta = $this->em->getClassMetadata(Commande::class);
            foreach (['prenom' => 'Client', 'nom' => 'anonymisé', 'email' => 'anonyme@anonymise.invalid'] as $field => $value) {
                $meta->setFieldValue($commande, $field, $value);
            }
            foreach (['telephone', 'note', 'livraisonAdresse', 'livraisonComplement', 'livraisonCodePostal', 'livraisonVille', 'livraisonTelephone', 'livraisonInstructions'] as $field) {
                $meta->setFieldValue($commande, $field, null);
            }
            $meta->setFieldValue($commande, 'cgvAcceptedIp', null); // la preuve (date, version) reste, pas l'adresse IP
            $commande->setUser(null);
        }
        $this->em->flush();

        // 3. Comptes (le titulaire + éventuels comptes de ses licenciés)
        foreach ($accounts as $account) {
            $this->wipeAccount($account);
        }
        $this->em->flush();
    }

    private function wipeFamille(Famille $famille): void
    {
        $meta = $this->em->getClassMetadata(Famille::class);
        $meta->setFieldValue($famille, 'nom', 'Famille anonymisée');
        foreach (['adresse', 'codePostal', 'ville', 'telephone', 'civilite', 'nomReprLegal2', 'telephoneReprLegal2', 'emailReprLegal2'] as $field) {
            $meta->setFieldValue($famille, $field, null);
        }
        // Le lien famille ↔ compte reste (obligatoire en base) : le compte, lui, est anonymisé juste après.
    }

    private function wipeLicencie(Licencie $licencie): void
    {
        $meta = $this->em->getClassMetadata(Licencie::class);
        $birth = $licencie->getDateNaissance();
        $meta->setFieldValue($licencie, 'nom', 'Anonymisé');
        $meta->setFieldValue($licencie, 'prenom', 'Licencié');
        // Seule l'année de naissance est conservée (nécessaire à la catégorie d'âge des statistiques).
        $meta->setFieldValue($licencie, 'dateNaissance', new \DateTimeImmutable(($birth ? $birth->format('Y') : '2000').'-01-01'));
        foreach (['numeroPersonne', 'numeroLicence', 'civilite', 'lieuNaissance', 'sexe', 'nationalite', 'telephone', 'emailIndividuel'] as $field) {
            $meta->setFieldValue($licencie, $field, null);
        }
        $meta->setFieldValue($licencie, 'droitImage', null);
        $meta->setFieldValue($licencie, 'droitImageAt', null);
        $meta->setFieldValue($licencie, 'autorisationParentaleAt', null);
        $meta->setFieldValue($licencie, 'actif', false);

        // Les licences gardent leurs montants ; on retire seulement le nom recopié dans l'intitulé.
        foreach ($this->adhesions->forLicencie($licencie) as $adhesion) {
            if ($adhesion->getLicencieLabel() !== null) {
                $adhesion->setLicencieLabel('Licencié anonymisé');
            }
        }
    }

    private function wipeAccount(User $account): void
    {
        $id = (int) $account->getId();

        // Messagerie : discussions supprimées (les documents joints disparaissent avec leurs messages)
        foreach ($this->conversations->findForUser($account) as $conversation) {
            $this->removeConversationFor($conversation, $account);
        }
        $this->em->flush();

        // Espace de documents personnels
        try {
            $folder = $this->storage->resolve($this->space->rootPath($account));
            if ($folder->exists && $folder->real !== null) {
                (new Filesystem())->remove($folder->real);
            }
        } catch (\Throwable) {
            // rien à supprimer
        }

        // Fichiers, notifications, clés d'accès, demandes de mot de passe : suppression directe
        foreach (['App\Entity\FileFavorite', 'App\Entity\NotificationState', 'App\Entity\WebauthnCredential', 'App\Entity\ResetPasswordRequest'] as $class) {
            $this->em->createQuery(sprintf('DELETE %s e WHERE e.user = :u', $class))->setParameter('u', $account)->execute();
        }
        // Journal d'audit : on garde les traces techniques mais plus l'identité
        $this->em->createQuery("UPDATE App\\Entity\\AuditLog l SET l.userEmail = NULL, l.userName = 'Compte anonymisé' WHERE l.userId = :id")->setParameter('id', $id)->execute();

        // Avatar
        if ($account->getAvatarName()) {
            $path = dirname(__DIR__, 3).'/public/uploads/avatars/'.$account->getAvatarName();
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $meta = $this->em->getClassMetadata(User::class);
        $set  = static fn (string $field, mixed $value) => $meta->setFieldValue($account, $field, $value);
        $set('email', sprintf('anonyme-%d@anonymise.invalid', $id));
        $set('nom', 'Compte anonymisé');
        $set('prenom', null);
        $set('bio', null);
        $set('avatarName', null);
        $set('roles', []);
        $set('password', password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT));
        $set('totpSecret', null);
        $set('emailAuthEnabled', false);
        $set('emailAuthCode', null);
        $set('backupCodes', []);
        $set('notificationPreferences', []);
        $set('tablePreferences', null);
        $set('lastSeenAt', null);
        $set('webauthnUserHandle', null);
        $set('permissionsAjoutees', []);
        $set('permissionsRetirees', []);
        $account->markAnonymized();
    }

    /** Supprime la discussion d'un compte ; dans un groupe, retire seulement ses messages et sa participation. */
    private function removeConversationFor(Conversation $conversation, User $account): void
    {
        if (!$conversation->isGroup()) {
            $this->em->remove($conversation);

            return;
        }
        $this->em->createQuery('DELETE App\Entity\Message m WHERE m.conversation = :c AND m.author = :u')->setParameter('c', $conversation)->setParameter('u', $account)->execute();
        if ($participant = $conversation->participantFor($account)) {
            $conversation->getParticipants()->removeElement($participant);
            $this->em->remove($participant);
        }
    }
}
