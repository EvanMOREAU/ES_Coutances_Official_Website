# ⚽ ES Coutances — Site officiel et gestion du club

Application web de l'**Entente Sportive Coutançaise** (Coutances, Manche) :

- **site public** : accueil, pages du club, encadrement, sponsors, contact, matchs en direct ;
- **boutique en ligne** : catalogue, panier, commande, paiement par carte (HelloAsso) ou au retrait ;
- **espace familles et licenciés** (`/mon-compte`) : licences, factures, planning, messagerie, commandes ;
- **back-office** (`/admin`) : licenciés, familles, saisons, adhésions, planning, boutique, partenaires, contrats, imports FootClub, fichiers, journal d'activité, mises à jour du site (avec sauvegardes et retour arrière automatique), sauvegardes de la base.

Développé par **Evan MOREAU**. Licence propriétaire (voir [LICENSE](LICENSE)).

---

## Stack

| Brique | Détail |
|---|---|
| PHP 8.4+ / Symfony 8.1 | Framework, Twig, Forms, Security, Messenger, Mailer |
| Doctrine ORM 3 + Migrations | MariaDB 10.11+ / MySQL |
| AssetMapper + Stimulus + Turbo | Pas de bundler Node ; polices, icônes et Trix hébergés dans `assets/` |
| TailwindCSS (symfonycasts/tailwind-bundle) | Styles du back-office |
| scheb/2fa + WebAuthn | Double authentification (appli TOTP, code par e-mail, codes de secours) et clés d'accès |
| VichUploader + LiipImagine | Envois d'images |
| PhpSpreadsheet, endroid/qr-code | Imports/exports Excel, QR codes |
| PHPUnit 13, PHPStan 2, PHP-CS-Fixer | Qualité (voir plus bas) |

## Installation (développement)

```bash
composer install
docker compose up -d                # MariaDB + Mailpit (facultatif si vous avez déjà une base locale)
cp .env .env.local                  # puis adapter DATABASE_URL, MAILER_DSN…
php bin/console doctrine:migrations:migrate
php bin/console tailwind:build --watch &
symfony server:start                # ou : php -S localhost:8000 -t public
php bin/console app:create-user     # crée le premier compte (rôle développeur)
php bin/console app:init-pages      # pages de contenu de base
```

Mailpit (boîte de réception de test) : <http://localhost:8025>.

## Qualité du code

```bash
composer check        # style + lint (YAML, Twig, conteneur) + PHPStan + tests — à lancer avant chaque commit
composer cs:fix       # corrige le style automatiquement
composer test:setup   # (re)crée la base de test, à refaire après chaque nouvelle migration
composer hooks:install  # active le contrôle avant commit (.githooks/pre-commit)
```

L'intégration continue (`.github/workflows/ci.yml`) rejoue le tout à chaque *push* et chaque *pull request*, plus `composer audit` (failles connues des dépendances). Dependabot propose chaque semaine les mises à jour.

Règles à connaître :

- toute **nouvelle route `/admin`** doit être déclarée dans `PermissionCatalog` et `RoutePermissions`, sinon `AccessControlTest` échoue ;
- tout **nouveau formulaire de création** est couvert par `EmptyFormsTest` (mettre `Assert\NotBlank` sur les colonnes `NOT NULL`) ;
- le moindre `<script>` en ligne doit porter `nonce="{{ csp_nonce() }}"` (voir *Sécurité*), pas de gestionnaire `onclick=""` ; une feuille de style CSS se déclare dans `templates/_importmap.html.twig`, pas via `import './x.css'` en JavaScript.

## Rôles et droits

| Rôle | Accès |
|---|---|
| `ROLE_DEV` | Tout, y compris mises à jour, sauvegardes de la base (réservées à ce rôle), boutique en maintenance, comptes |
| `ROLE_ADMIN` | Back-office complet (restreignable par profil d'autorisation) |
| `ROLE_EDITOR` | Back-office selon son profil d'autorisation (`Administration > Accès`) |
| `ROLE_FAMILLE` / `ROLE_LICENCIE` / compte boutique | Espace `/mon-compte` uniquement |

Les comptes **développeur et administrateur doivent activer une double authentification** (application, code par e-mail ou clé d'accès) : sans cela, le back-office les renvoie vers *Paramètres > Sécurité* (`ENFORCE_PRIVILEGED_2FA`, activé par défaut).

## Configuration (variables d'environnement)

À définir dans `.env.local` sur le serveur (jamais de secret de production dans un fichier versionné) :

| Variable | Rôle |
|---|---|
| `APP_ENV=prod`, `APP_SECRET` | Environnement et clé secrète (⚠ elle chiffre aussi les mots de passe SMTP/HelloAsso stockés en base : ne pas la changer sans les ressaisir) |
| `DATABASE_URL` | Connexion à la base |
| `MAILER_DSN`, `MAILER_FROM_ADDRESS`, `MAILER_FROM_NAME` | E-mails (le SMTP peut aussi être réglé dans l'admin) |
| `MAINTENANCE_FLAG_PATH` | Fichier dont l'existence active la page de maintenance 503 |
| `DEPLOY_BRANCH`, `COMPOSER_BIN`, `PHP_CLI_BIN` | Mise à jour depuis l'admin |
| `FILES_QUOTA_MB` | Quota du gestionnaire de fichiers |
| `ENFORCE_PRIVILEGED_2FA` | `0` pour suspendre la double authentification obligatoire des comptes privilégiés |
| `LEGAL_PUBLICATION_DIRECTOR`, `LEGAL_ASSOCIATION_ID`, `LEGAL_HOST` | Mentions légales obligatoires : `app:securite:verifier` échoue si elles sont vides |
| `LEGAL_CONSUMER_MEDIATOR`, `LEGAL_DPO_CONTACT`, `LEGAL_VAT_MENTION` | Médiateur de la consommation (obligatoire pour vendre à des consommateurs), contact RGPD, mention de TVA des factures |

HelloAsso (paiement en ligne) se configure dans l'admin (`Configuration > HelloAsso`) ; renseigner l'URL de notification `…/boutique/helloasso/notification` côté HelloAsso.

## Pages légales

`/mentions-legales`, `/politique-de-confidentialite`, `/conditions-de-vente` et `/cookies` ont un texte par défaut (`templates/legal/`). Pour le remplacer sans toucher au code : créer dans l'admin une page de contenu dont l'**adresse (slug)** est `mentions-legales`, `confidentialite`, `conditions-de-vente` ou `cookies`.

## Sécurité

- connexion limitée à 5 essais / 15 min (par compte et par IP) ; formulaires publics limités par IP (`framework.rate_limiter`, `PublicFormThrottleListener`) ; pot de miel sur le formulaire de contact ;
- **Content-Security-Policy** stricte (`SecurityHeadersListener`) : scripts limités au site et à ceux portant le *nonce* de la requête, aucune ressource externe (polices, icônes et éditeur Trix sont locaux) ; autres en-têtes : HSTS, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `nosniff` ;
- double authentification, clés d'accès, codes de secours ; mots de passe hachés (`auto`) ;
- journal d'activité (`/admin/journal`), conservé 12 mois (`app:audit:purger`) ;
- mise à jour : un e-mail récapitulatif part vers tous les comptes développeur à chaque mise à jour, réussie ou non ;
- RGPD : export des données et suppression/anonymisation du compte depuis l'espace connecté.

## Conformité

Consentements enregistrés (CGV, création de compte), factures numérotées, droit à l'image, purge des comptes inactifs et dossier RGPD (registre des traitements, sous-traitants, violations, demandes de droits, charte) : voir **[docs/conformite/](docs/conformite/README.md)**, qui liste aussi ce qu'il reste à faire côté club.

## Exploitation

Voir **[docs/EXPLOITATION.md](docs/EXPLOITATION.md)** : mise en production, mise à jour, sauvegardes et retour arrière automatique, tâches planifiées, sauvegardes et restauration, supervision, procédure en cas d'incident.

Fichiers de référence : [`deploy/nginx.conf.example`](deploy/nginx.conf.example), [`public/.htaccess`](public/.htaccess) (Apache), [`deploy/backup.sh`](deploy/backup.sh), [`deploy/crontab.example`](deploy/crontab.example).

## Structure

```
src/
  Audit/          journal d'activité (écouteurs Doctrine, requêtes, sécurité)
  Command/        commandes console (utilisateurs, pages, saison, purges, déploiement)
  Controller/     public, portail (/mon-compte), Admin/, Security/
  Entity/ Repository/ Form/
  EventListener/  maintenance, permissions, en-têtes de sécurité, limitation de débit, indexation
  Mail/           expéditeur dynamique (SMTP réglable dans l'admin)
  Security/       voters, catalogue des permissions, WebAuthn, double authentification
  Service/        planning, boutique, import FootClub, déploiement, messagerie, HelloAsso, RGPD…
  Twig/           extensions (dont csp_nonce)
templates/        gabarits (admin/, boutique/, club/, portail/, legal/, email/, bundles/TwigBundle/Exception/)
assets/           JavaScript (Stimulus), CSS, polices et bibliothèques hébergées (vendor/)
migrations/       migrations Doctrine
tests/            Unit/ et Functional/ (base de test dédiée)
deploy/ docs/     serveur web, sauvegardes, planification, procédures
changelog/        notes de version affichées dans l'admin (releases.yaml)
```
