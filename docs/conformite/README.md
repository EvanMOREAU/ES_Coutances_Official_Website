# Conformité (RGPD, vente en ligne) — dossier du club

Ces documents sont des **modèles à compléter et à signer par le président** (ou le responsable désigné) : le code ne peut pas à lui seul rendre un club conforme. À relire une fois par saison et à chaque changement de prestataire ou de traitement. Ils ne remplacent pas l'avis d'un juriste ni du délégué à la protection des données de la fédération.

| Document | À quoi il sert | Obligation |
|---|---|---|
| [registre-traitements.md](registre-traitements.md) | Registre des activités de traitement + examen de la nécessité d'une AIPD | RGPD art. 30 et 35 |
| [sous-traitants.md](sous-traitants.md) | Liste des prestataires et état des contrats | RGPD art. 28 |
| [violations.md](violations.md) | Registre des violations de données et modèles de notification | RGPD art. 33-34 |
| [demandes-droits.md](demandes-droits.md) | Registre des demandes d'exercice des droits (délai : 1 mois) | RGPD art. 12 à 22 |
| [charte-benevoles.md](charte-benevoles.md) | Règles à faire accepter aux bénévoles et dirigeants ayant un accès | Sécurité (art. 32) |
| [reprise-activite.md](reprise-activite.md) | Objectifs de reprise (RTO/RPO) et journal des tests de restauration | Sécurité (art. 32) |

## Ce que fait déjà l'application

| Sujet | Mise en œuvre |
|---|---|
| Consentement aux conditions de vente | Case obligatoire à la commande ; date, version du texte (`App\Legal\LegalVersion`) et adresse IP enregistrées sur la commande |
| Consentement à la création d'un compte | Case obligatoire ; date et version enregistrées sur le compte |
| Obligation de paiement | Bouton « Commander avec obligation de paiement » |
| Rétractation | Formulaire type, frais de retour et exceptions dans les conditions de vente |
| Facture | Numéro séquentiel `FAC-AAAA-NNNNN` attribué à l'encaissement, facture imprimable depuis la page de suivi de la commande |
| Droit à l'image / autorisation parentale | Champs sur la fiche du licencié (Administration > Licenciés) ; non renseigné = refus |
| Durées de conservation | `app:audit:purger` (12 mois), `app:messagerie:purger-documents` (14 jours), `app:rgpd:purger-inactifs` (comptes sans connexion depuis 36 mois) |
| Droits des personnes | Export et suppression/anonymisation du compte (Paramètres > Confidentialité) |
| Carte OpenStreetMap | Chargée uniquement après un clic du visiteur |
| Mentions obligatoires | `app:securite:verifier` signale (et le déploiement affiche) les variables `LEGAL_*` manquantes |

## À faire côté club (hors code)

1. Renseigner dans `.env.local` : `LEGAL_PUBLICATION_DIRECTOR`, `LEGAL_ASSOCIATION_ID`, `LEGAL_HOST`, `LEGAL_CONSUMER_MEDIATOR`, `LEGAL_DPO_CONTACT`, et confirmer `LEGAL_VAT_MENTION` avec le trésorier (la mention par défaut suppose une association hors champ de la TVA).
2. Choisir un **médiateur de la consommation** (adhésion à un médiateur référencé, liste de la Commission d'évaluation et de contrôle de la médiation de la consommation) : obligatoire dès que le club vend à des consommateurs.
3. Compléter et signer les documents de ce dossier ; conserver les contrats de sous-traitance (hébergeur, SMTP, HelloAsso, stockage des sauvegardes).
4. Recueillir **par écrit** l'autorisation parentale et le droit à l'image à l'inscription (formulaire papier ou numérique), puis les saisir sur la fiche du licencié. Ne pas publier de photo d'un licencié dont le droit à l'image n'est pas « Autorisé ».
5. Brancher la supervision (Sentry/GlitchTip, sonde de disponibilité sur `/health`) : voir `docs/EXPLOITATION.md` §5.
6. Faire réaliser un audit d'accessibilité (RGAA) si le club y est soumis, puis publier la déclaration d'accessibilité ; faire réaliser un test d'intrusion avant toute ouverture à grande échelle.
7. Ajouter `app:rgpd:purger-inactifs` aux tâches planifiées (`deploy/crontab.example`) et lancer d'abord `--dry-run`.
