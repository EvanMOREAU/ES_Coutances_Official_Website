# Registre des activités de traitement (RGPD, article 30)

Responsable du traitement : **Entente Sportive Coutançaise** — représentée par : ____________ (président) — contact RGPD : ____________
Dernière mise à jour : ____/____/______ — version des textes légaux du site : voir `src/Legal/LegalVersion.php`

| # | Traitement | Finalité | Personnes concernées | Données | Base légale | Destinataires | Durée de conservation | Sécurité / mesures |
|---|---|---|---|---|---|---|---|---|
| 1 | Gestion des adhésions et licences | Inscrire les joueurs, délivrer les licences, composer les équipes | Licenciés (dont mineurs), représentants légaux | Identité, date et lieu de naissance, nationalité, adresse, téléphone, e-mail, catégorie, n° de licence | Contrat (adhésion) | Dirigeants habilités, FFF (licences) | Durée de l'adhésion ; anonymisation après 36 mois sans connexion ; pièces comptables 10 ans anonymisées | Accès par profil, double authentification des comptes de gestion, journal d'audit |
| 2 | Facturation des cotisations | Facturer licences, règlements et aides | Familles, licenciés adultes | Montants, règlements, aides, nom sur la facture | Obligation légale | Trésorier, expert-comptable | 10 ans | Idem ; conservation anonymisée après effacement du compte |
| 3 | Droit à l'image | Publier photos/vidéos (site, matchs en direct) | Licenciés, représentants légaux | Autorisation ou refus, date | Consentement | Public du site (si autorisé) | Jusqu'au retrait ou fin d'adhésion | Non renseigné = refus ; retrait possible à tout moment |
| 4 | Boutique en ligne | Vendre et remettre les articles du club | Clients | Identité, e-mail, téléphone, commande, mode de règlement, preuve d'acceptation des CGV (date, version, IP) | Contrat ; obligation légale | Bureau, HelloAsso (paiement carte) | 10 ans (facturation) | HTTPS, paiement délégué à HelloAsso (le club ne voit jamais la carte) |
| 5 | Comptes utilisateurs | Accès à l'espace famille/licencié/boutique | Titulaires de comptes | E-mail, mot de passe haché, préférences, dernière connexion | Contrat | Administrateurs | Suppression sur demande ; anonymisation après 36 mois d'inactivité | Hachage, limitation des essais, double authentification |
| 6 | Messagerie interne | Échanges entre le club et les familles | Familles, dirigeants | Messages, documents joints | Contrat | Participants à la conversation | Documents 14 jours ; messages jusqu'à la suppression du compte | Accès restreint aux participants |
| 7 | Formulaire de contact | Répondre aux demandes | Visiteurs | Nom, e-mail, téléphone, message | Intérêt légitime | Bureau | 6 mois après traitement (boîte e-mail) | Limitation de débit, pot de miel |
| 8 | Sécurité et journalisation | Détecter les abus, tracer les actions de gestion | Comptes de gestion, visiteurs | Adresse IP, connexions, actions | Intérêt légitime | Développeurs | 12 mois | Purge automatique `app:audit:purger` |
| 9 | Partenaires et contrats | Gérer sponsors et contrats | Représentants des partenaires | Identité, coordonnées, contrats, règlements | Contrat | Bureau, trésorier | Durée du contrat + 5 ans | Accès par profil |
| 10 | Offres d'emploi / candidatures (si utilisé) | Recruter | Candidats | CV, coordonnées | Mesures précontractuelles | Bureau | 2 ans maximum après le dernier contact | Accès restreint |

## Examen de la nécessité d'une analyse d'impact (AIPD, article 35)

| Critère (lignes directrices du G29 / CNIL) | Réponse | Commentaire |
|---|---|---|
| Données sensibles ou à caractère hautement personnel | Non (pas de santé dans l'application) | Si un certificat médical ou un questionnaire de santé est numérisé : **oui, AIPD requise** |
| Données concernant des personnes vulnérables (mineurs) | Oui | Critère retenu |
| Traitement à grande échelle | Non (club local, quelques centaines de personnes) | À réévaluer si le volume augmente |
| Profilage, décisions automatisées, surveillance systématique | Non | — |
| Croisement de jeux de données, usage innovant | Non | — |

Conclusion : un seul critère est rempli ; une AIPD n'est pas obligatoire en l'état. **À refaire** avant d'ajouter des données de santé, de la vidéo-surveillance ou un traitement à plus grande échelle. Décision prise par : ____________ le ____/____/______.
