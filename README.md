# ⚽ ES Coutances — Site Officiel

Site web officiel de l'**Entente Sportive Coutançaise**, club de football basé à Coutances (50200), Normandie.  
Développé par **Evan MOREAU**.

---

## 🛠️ Stack technique

| Technologie | Version | Rôle |
|---|---|---|
| PHP | 8.4+ | Langage serveur |
| Symfony | 8.1.x | Framework principal |
| Doctrine ORM | 3.x | ORM / Base de données |
| MySQL / MariaDB | 10.11+ | Base de données |
| EasyAdmin | 5.4 | Back-office administration |
| VichUploader | — | Gestion des uploads d'images |
| Symfony Mailer | — | Envoi d'emails (contact) |
| Symfony AssetMapper | — | Gestion des assets (pas de bundler Node) |
| Twig | 3.x | Moteur de templates |
| CSS natif | — | Pas de Bootstrap — Grid/Flexbox pur |
| Font Awesome | 6.5 | Icônes |
| Barlow Condensed + Inter | — | Typographies (Google Fonts) |

---

## 📁 Structure du projet

```
escoutances_symfony/
├── config/
│   ├── packages/
│   │   ├── security.yaml
│   │   └── vich_uploader.yaml
│   └── services.yaml
├── public/
│   ├── index.php
│   ├── images/
│   │   └── favicon.png
│   ├── css/
│   │   └── admin-theme.css   ← thème visuel EasyAdmin (accent marque, sidebar, KPI...)
│   └── uploads/            ← gitignore
│       ├── slides/
│       ├── offres/
│       ├── membres/
│       ├── partenaires/
│       ├── banniere/
│       └── pages/
├── src/
│   ├── Command/
│   │   ├── CreateUserCommand.php
│   │   └── InitPagesCommand.php
│   ├── Controller/
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── CategorieCrudController.php
│   │   │   ├── MembreCrudController.php
│   │   │   ├── OffreEmploiCrudController.php
│   │   │   ├── PageContenuCrudController.php
│   │   │   ├── PartenaireCrudController.php
│   │   │   ├── ReglagesController.php
│   │   │   ├── SlideCarouselCrudController.php
│   │   │   └── UserCrudController.php
│   │   ├── ClubController.php
│   │   ├── ContactController.php
│   │   ├── DefaultController.php
│   │   └── LoginController.php
│   ├── Entity/
│   │   ├── Categorie.php
│   │   ├── HomepageBanner.php
│   │   ├── Membre.php
│   │   ├── OffreEmploi.php
│   │   ├── PageContenu.php
│   │   ├── Partenaire.php
│   │   ├── SlideCarousel.php
│   │   └── User.php
│   ├── Form/
│   │   └── ContactType.php
│   ├── Repository/
│   ├── Security/
│   │   └── UserVoter.php
│   └── Service/
│       └── OrdreService.php
├── templates/
│   ├── admin/
│   │   ├── dashboard.html.twig
│   │   └── reglages/
│   │       ├── _layout.html.twig
│   │       ├── accueil.html.twig
│   │       ├── contact.html.twig
│   │       ├── match_live.html.twig
│   │       └── compte.html.twig
│   ├── club/
│   │   ├── _layout.html.twig
│   │   ├── encadrement.html.twig
│   │   ├── histoire.html.twig
│   │   └── infrastructure.html.twig
│   ├── contact/
│   │   └── index.html.twig
│   ├── default/
│   │   └── index.html.twig
│   ├── login/
│   │   └── index.html.twig
│   └── base.html.twig
├── .env
├── .env.local          ← JAMAIS commité
├── .gitignore
├── LICENSE
└── composer.json
```

---

## 🗃️ Entités Doctrine

| Entité | Description |
|---|---|
| `User` | Utilisateurs du back-office |
| `OffreEmploi` | Offres d'emploi / formations |
| `SlideCarousel` | Slides du carousel hero |
| `PageContenu` | Pages éditables (Histoire, Infrastructure) |
| `Membre` | Membres de l'encadrement |
| `Categorie` | Catégories d'encadrement (Senior, Académie...) |
| `Partenaire` | Partenaires & sponsors (nom, logo, url) |
| `HomepageBanner` | Bannière image + lien affichée en page d'accueil — ligne unique éditée via l'onglet "Accueil" de `/admin/reglages` |

---

## 👥 Rôles utilisateurs

| Rôle | Accès |
|---|---|
| `ROLE_DEV` | Accès total + gestion des comptes développeurs + catégories d'encadrement |
| `ROLE_ADMIN` | Accès au back-office métier (partenaires, offres, carousel, pages, encadrement, réglages) |
| `ROLE_EDITOR` | Accès limité au back-office (`/admin`) |
| `ROLE_USER` | Rôle de base (hérité par tous) |

**Hiérarchie :** `ROLE_DEV` → `ROLE_ADMIN` → `ROLE_EDITOR` → `ROLE_USER`

**Accès admin :** raccourci clavier `Ctrl + Shift + A` depuis n'importe quelle page du site.

**Sécurité utilisateurs :**
- Seul un `ROLE_DEV` peut modifier ou supprimer un compte `ROLE_DEV`
- Le bouton Delete/Edit est masqué visuellement pour les non-dev (via `UserVoter`)
- Les comptes dev apparaissent toujours en premier dans la liste

---

## 🎨 Panel admin

- **Thème visuel** : `public/css/admin-theme.css` re-thème EasyAdmin via son système de design tokens (accent couleur de la marque, sidebar sombre, cartes KPI sur le tableau de bord) — chargé depuis `DashboardController::configureAssets()`.
- **Réglages unifiés** : `ReglagesController` regroupe la bannière d'accueil, la page de contact, le bandeau "Match en Live" et le changement de mot de passe sous une seule entrée de menu (`/admin/reglages`) avec navigation par onglets, plutôt que 4 pages isolées.

---

## ⚙️ Installation

### Prérequis

- PHP 8.4+
- Composer
- MySQL / MariaDB
- Symfony CLI (optionnel)

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/EvanMOREAU/ES_Coutances_Official_Website.git
cd ES_Coutances_Official_Website

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec tes valeurs (DATABASE_URL, MAILER_DSN...)

# 4. Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Initialiser les pages de contenu
php bin/console app:init-pages

# 6. Créer le premier utilisateur développeur
php bin/console app:create-user dev@exemple.fr MotDePasse "Prénom Nom" ROLE_DEV

# 7. Créer les dossiers d'uploads
mkdir -p public/uploads/slides public/uploads/offres public/uploads/membres public/uploads/partenaires public/uploads/banniere public/uploads/pages

# 8. Vider le cache
php bin/console cache:clear

# 9. Lancer le serveur
symfony server:start
```

---

## 🔐 Variables d'environnement

```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/escoutances_symfony?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://localhost:1025
```

> ⚠️ **Ne jamais commiter `.env.local`**

---

## 📱 Pages du site

| URL | Description |
|---|---|
| `/` | Page d'accueil (carousel, bannière image + lien, offres, partenaires) |
| `/club/histoire` | Histoire du club (éditable, avec image d'en-tête et chapô) |
| `/club/encadrement` | Encadrement par catégorie |
| `/club/infrastructure` | Infrastructure (éditable, avec image d'en-tête et chapô) |
| `/contact` | Formulaire de contact |
| `/admin` | Back-office EasyAdmin |
| `/admin/login` | Page de connexion admin |
| `/admin/reglages/accueil` | Réglage de la bannière image + lien de la page d'accueil |
| `/admin/reglages/contact` | Réglage des informations de la page de contact |
| `/admin/reglages/match-live` | Réglage du bandeau "Match en Live" |
| `/admin/reglages/mon-compte` | Changement du mot de passe du compte connecté |

---

## 🔧 Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Créer un utilisateur
php bin/console app:create-user email@exemple.fr MotDePasse "Prénom Nom" ROLE_DEV

# Migration après modification d'entité
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Lister les routes
php bin/console debug:router
```

---

## 🖼️ Gestion des images

| Mapping | Dossier | Utilisé pour |
|---|---|---|
| `slide_image` | `/uploads/slides/` | Carousel hero |
| `offre_image` | `/uploads/offres/` | Offres d'emploi |
| `membre_photo` | `/uploads/membres/` | Encadrement |
| `partenaire_logo` | `/uploads/partenaires/` | Partenaires & sponsors |
| `homepage_banner_image` | `/uploads/banniere/` | Bannière image + lien de la page d'accueil |
| `page_contenu_image` | `/uploads/pages/` | Image d'en-tête des pages éditables (Histoire, Infrastructure) |

---

## ⚡ Optimisations en place

- **`loading="lazy"`** sur toutes les images hors viewport initial
- **`loading="eager"`** sur les slides hero et le logo
- **Cache HTTP 5 min** sur la page d'accueil
- **Animations au scroll** (fade/slide-in) et effet Ken Burns sur le carousel hero
- **OrdreService** — gestion automatique et unique des ordres d'affichage

---

## 🔢 Gestion de l'ordre d'affichage (OrdreService)

- **Auto-incrémentation** à la création (dernier ordre + 1)
- **Unicité garantie** — si un ordre est déjà pris, les suivants sont décalés de +1
- **Entités concernées** : `Categorie`, `Membre`, `SlideCarousel`

---

## ✅ Qualité du code et tests

Un seul réflexe avant de livrer :

```bash
composer check      # style + lint + analyse statique + tests
```

| Commande | Rôle |
|---|---|
| `composer cs` / `composer cs:fix` | Vérifie / corrige le style (PHP CS Fixer, règles dans `.php-cs-fixer.dist.php`) |
| `composer lint` | Valide les fichiers YAML, les templates Twig et le conteneur de services |
| `composer stan` | Analyse statique PHPStan (niveau 5, `phpstan.dist.neon`) |
| `composer test:setup` | Crée la base `…_test` et rejoue **toutes les migrations** depuis zéro (à lancer une fois, puis après chaque nouvelle migration) |
| `composer test` | Lance toute la suite PHPUnit |
| `composer test:unit` | Tests unitaires seuls (rapides, sans base de données) |
| `composer hooks:install` | Active le contrôle automatique (style, analyse, tests unitaires) avant chaque `git commit` |

### Organisation des tests

```
tests/
├── Unit/         # Logique pure : montants, catégories d'âge, autorisations, voters, fichiers, import, HelloAsso (HTTP simulé)…
├── Functional/   # Parcours HTTP complets sur une vraie base : connexion, boutique, import, RGPD, messagerie, administration…
└── Support/      # DatabaseTestCase (rollback automatique après chaque test) et fabriques de données
```

Quelques tests « de masse » protègent l'ensemble de l'application :

- **`AccessControlTest`** parcourt *toutes* les routes : aucune page du back-office ou de l'espace Mon compte ne s'ouvre sans connexion, une route sans autorisation associée est refusée, et aucune ne répond en erreur 500.
- **`EmptyFormsTest`** envoie à vide chaque formulaire de création du back-office : une validation oubliée devient un message pour l'utilisateur, jamais une erreur SQL.
- **`DoctrineMappingTest`** échoue si une entité a changé sans migration.

La base de test (`escoutances_symfony_test`) est distincte de la base de développement. En intégration continue (`.github/workflows/ci.yml`), chaque push et chaque pull request lancent le contrôle de style, le lint, PHPStan, `composer audit` et la suite de tests sur MariaDB.

> **Ajouter une page au back-office ?** Déclarez son autorisation dans `PermissionCatalog` puis dans `RoutePermissions` : sinon `AccessControlTest` signale la route comme non protégée.

### Supervision

`GET /health` répond `200 {"status":"ok"}` quand le site et la base fonctionnent (`503` sinon) : à brancher sur un outil de supervision type UptimeRobot.

---

## 🚀 Déploiement en production

```bash
APP_ENV=prod
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console doctrine:migrations:migrate --env=prod
```

---

## 👨‍💻 Développeur

**Evan MOREAU**  
📧 evan.moreau@etik.com

---

## 📄 Licence

Projet propriétaire — © 2026 Evan MOREAU.  
Tous droits réservés. Voir le fichier [LICENSE](./LICENSE) pour les détails.

https://chatgpt.com/share/6ab671b1-e414-83ed-9a36-a889fb44e862