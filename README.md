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
| VichUploader + LiipImagine | Envois d'images ; miniatures WebP à la demande (filtre Twig `|thumb`) |
| PhpSpreadsheet, endroid/qr-code | Imports/exports Excel, QR codes |
| PHPUnit 13, PHPStan 2 (niveau 7), PHP-CS-Fixer, Playwright | Qualité : tests unitaires et fonctionnels, tests de bout en bout dans un navigateur (voir plus bas) |
| Sentry (facultatif) | Suivi des erreurs en production, activé par `SENTRY_DSN` |

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

L'intégration continue (`.github/workflows/ci.yml`) rejoue le tout à chaque *push* et chaque *pull request* : style, lint, PHPStan, tests (PHP 8.4 et 8.5, avec couverture), build de production (`--no-dev`, cache, assets), tests de bout en bout dans un navigateur, plus `composer audit` (failles connues des dépendances) et la liste des mises à jour majeures. Dependabot propose chaque semaine les mises à jour.

```bash
cd e2e && npm install && E2E_CHANNEL=msedge npx playwright test   # parcours de bout en bout (E2E_CHANNEL=chrome|msedge évite de télécharger Chromium)
```

Les tests E2E (`e2e/`) utilisent leur propre base (`*_teste2e`, créée et migrée automatiquement) : ils parcourent le site public, la commande boutique de bout en bout, la connexion à l'administration, et échouent à la moindre erreur JavaScript ou violation de Content-Security-Policy.

**Couverture** : le plancher est dans `deploy/coverage-floor.txt` (la CI échoue en dessous) ; le résumé du job propose la nouvelle valeur quand la couverture progresse.

**Tests en parallèle** : une même base ne supporte pas deux suites PHPUnit simultanées (transaction de test, compteurs de limitation). Pour en lancer plusieurs, donner à chacune sa base avec `TEST_TOKEN` (ex. `TEST_TOKEN=a composer test:setup` puis `TEST_TOKEN=a vendor/bin/phpunit`).

Règles à connaître :

- toute **nouvelle route `/admin`** doit être déclarée dans `PermissionCatalog` et `RoutePermissions`, sinon `AccessControlTest` échoue ;
- tout **nouveau formulaire de création** est couvert par `EmptyFormsTest` (mettre `Assert\NotBlank` sur les colonnes `NOT NULL`) ;
- aucun attribut `style=""` ni `<script>` en ligne (la CSP les interdit, `StrictCspTest` le vérifie) : les styles vont dans une classe CSS (`assets/styles/`), les scripts dans un fichier (`assets/pages/` pour les pages publiques, `assets/controllers/` pour Stimulus). Un `<style>` de page doit porter `nonce="{{ csp_nonce() }}"`. Une feuille de style CSS se déclare dans `templates/_importmap.html.twig`, pas via `import './x.css'` en JavaScript ;
- une taille ou une largeur dynamique se fait avec les classes utilitaires de `assets/styles/inline-utilities.css` (`pct-NN`, `reveal-d-N`…) ;
- une image envoyée par un utilisateur s'affiche avec `{{ ('uploads/dossier/'~nom)|thumb('carte') }}` (filtres dans `config/packages/liip_imagine.yaml`).

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
| `MAILER_ASYNC` | `1` : les e-mails sont mis en file d'attente et envoyés par un *worker* (cron `messenger:consume`, voir `deploy/crontab.example`) ; `0` (défaut) : envoi direct |
| `SENTRY_DSN` | Suivi des erreurs en production (Sentry ou GlitchTip) ; vide = désactivé. Aucune donnée personnelle n'est transmise |
| `DEPLOY_FROM_ADMIN` | `0` désactive le bouton *Mise à jour* de l'administration (déploiement par un pipeline externe : plus de code exécuté depuis le site) |

HelloAsso (paiement en ligne) se configure dans l'admin (`Configuration > HelloAsso`) ; renseigner l'URL de notification `…/boutique/helloasso/notification` côté HelloAsso.

## Pages légales

`/mentions-legales`, `/politique-de-confidentialite`, `/conditions-de-vente` et `/cookies` ont un texte par défaut (`templates/legal/`). Pour le remplacer sans toucher au code : créer dans l'admin une page de contenu dont l'**adresse (slug)** est `mentions-legales`, `confidentialite`, `conditions-de-vente` ou `cookies`.

## Sécurité

- connexion limitée à 5 essais / 15 min (par compte et par IP) ; formulaires publics limités par IP (`framework.rate_limiter`, `PublicFormThrottleListener`) ; pot de miel sur le formulaire de contact ;
- **Content-Security-Policy** stricte (`SecurityHeadersListener`) : scripts et feuilles de style limités au site et à ceux portant le *nonce* de la requête, **aucun style ni gestionnaire en ligne** (`style-src-attr 'none'`), aucune ressource externe (polices, icônes et éditeur Trix sont locaux) ; autres en-têtes : HSTS, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `nosniff`. Navigation Turbo : le navigateur renvoie le nonce courant (en-tête `X-Csp-Nonce`, `assets/csp_nonce.js`) pour que les pages suivantes, comme les styles créés par Turbo et Trix, restent autorisés (`App\Security\CspNonce`) ;
- documents confidentiels (contrats des partenaires) stockés hors du dossier public, dans `var/private/`, servis après contrôle des droits ;
- double authentification, clés d'accès, codes de secours ; mots de passe hachés (`auto`) ;
- journal d'activité (`/admin/journal`), conservé 12 mois (`app:audit:purger`) ; les exports (journal, comptabilité) y sont eux-mêmes consignés ;
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
  Message/ MessageHandler/  e-mails mis en file d'attente (MAILER_ASYNC)
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
assets/           JavaScript (Stimulus : controllers/ ; scripts de pages publiques : pages/ ; fonctions pures : lib/), CSS, polices et bibliothèques hébergées (vendor/)
e2e/              tests de bout en bout (Playwright)
Dockerfile, compose.prod.example.yaml, deploy/docker/   image de production (PHP-FPM + nginx + worker)
migrations/       migrations Doctrine
tests/            Unit/ et Functional/ (base de test dédiée)
public/           manifest.webmanifest, sw.js, offline.html : application installable (aucune page ni donnée privée n'est mise en cache)
deploy/ docs/     serveur web, sauvegardes, planification, procédures
changelog/        notes de version affichées dans l'admin (releases.yaml)
```
