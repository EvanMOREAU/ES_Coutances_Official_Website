# ⚽ ES Coutances — Site Officiel

Site web officiel de l'**Entente Sportive Coutançaise**, club de football basé à Coutances (50200), Normandie.  
Développé par **Cylaos ICT** — Evan MOREAU.

---

## 🛠️ Stack technique

| Technologie | Version | Rôle |
|---|---|---|
| PHP | 8.2+ | Langage serveur |
| Symfony | 8.1.x | Framework principal |
| Doctrine ORM | 3.x | ORM / Base de données |
| MySQL / MariaDB | 8.x | Base de données |
| EasyAdmin | 5.x | Back-office administration |
| VichUploader | — | Gestion des uploads d'images |
| Symfony Mailer | — | Envoi d'emails (contact, partenariat) |
| Symfony Cache | — | Cache des appels API Facebook |
| Symfony HttpClient | — | Appels API externes (Facebook Graph) |
| Twig | 3.x | Moteur de templates |
| CSS natif | — | Pas de Bootstrap — Grid/Flexbox pur |
| Font Awesome | 6.5 | Icônes |
| Barlow Condensed + Inter | — | Typographies (Google Fonts) |

---

## 📁 Structure du projet

```
escoutances_symfony/
├── config/                  # Configuration Symfony
│   ├── packages/
│   │   ├── security.yaml    # Rôles, firewalls, hiérarchie
│   │   └── vich_uploader.yaml
│   └── services.yaml        # Services (FacebookService...)
├── public/
│   ├── index.php
│   ├── images/              # Images statiques (logo, fallbacks)
│   └── uploads/             # Uploads dynamiques (gitignore)
│       ├── slides/
│       ├── offres/
│       ├── membres/
│       └── partenaires/
├── src/
│   ├── Controller/
│   │   ├── Admin/           # Controllers EasyAdmin
│   │   ├── ClubController.php
│   │   ├── ContactController.php
│   │   ├── HomeController.php
│   │   └── NavController.php
│   ├── Entity/              # Entités Doctrine
│   ├── Form/                # FormTypes Symfony
│   ├── Repository/          # Repositories Doctrine
│   ├── Security/
│   │   └── UserVoter.php    # Gestion droits utilisateurs
│   └── Service/
│       └── FacebookService.php
├── templates/
│   ├── admin/               # Templates EasyAdmin custom
│   ├── actualite/           # Pages actualités (si actif)
│   ├── club/                # Pages club (Histoire, Encadrement...)
│   ├── contact/
│   ├── home/
│   │   └── index.html.twig  # Page d'accueil
│   ├── nav/                 # Fragments nav (équipes dynamiques)
│   └── base.html.twig       # Template de base
├── .env                     # Variables d'environnement (sans secrets)
├── .env.local               # Variables locales (JAMAIS commité)
└── composer.json
```

---

## 🗃️ Entités Doctrine

| Entité | Description |
|---|---|
| `User` | Utilisateurs du back-office |
| `Equipe` | Équipes du club (catégorie, niveau, lien FFF) |
| `OffreEmploi` | Offres d'emploi / formations |
| `SlideCarousel` | Slides du carousel hero |
| `PageContenu` | Pages éditables (Histoire, Infrastructure) |
| `Membre` | Membres de l'encadrement |
| `Categorie` | Catégories d'encadrement (Senior, Académie...) |
| `Partenaire` | Partenaires & sponsors (logo, url) |

---

## 👥 Rôles utilisateurs

| Rôle | Accès |
|---|---|
| `ROLE_DEV` | Accès total + gestion des comptes développeurs |
| `ROLE_ADMIN` | Accès à tout le back-office sauf comptes dev |
| `ROLE_EDITOR` | Accès limité (actualités, photos) |
| `ROLE_USER` | Rôle de base (hérité par tous) |

**Hiérarchie :** `ROLE_DEV` → `ROLE_ADMIN` → `ROLE_EDITOR` → `ROLE_USER`

**Accès admin :** raccourci clavier `Ctrl + Shift + A` depuis n'importe quelle page du site.

---

## ⚙️ Installation

### Prérequis

- PHP 8.2+
- Composer
- MySQL / MariaDB
- Symfony CLI (optionnel)

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/EvanMOREAU/Football_Website_Symfony.git
cd Football_Website_Symfony

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec tes valeurs (BDD, mailer, Facebook...)

# 4. Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Initialiser les pages de contenu
php bin/console app:init-pages

# 6. Créer le premier utilisateur développeur
php bin/console app:create-user dev@exemple.fr MotDePasse "Prénom Nom" ROLE_DEV

# 7. Créer les dossiers d'uploads
mkdir -p public/uploads/slides public/uploads/offres public/uploads/membres public/uploads/partenaires

# 8. Vider le cache
php -d memory_limit=512M bin/console cache:clear

# 9. Lancer le serveur de développement
symfony server:start
# ou
php -S localhost:8000 -t public/
```

---

## 🔐 Variables d'environnement

Copier `.env` vers `.env.local` et remplir :

```env
# Base de données
DATABASE_URL="mysql://user:password@127.0.0.1:3306/escoutances"

# Mailer (contact & partenariat)
MAILER_DSN=smtp://localhost:1025
# En production :
# MAILER_DSN=smtp://user:password@smtp.escoutances.fr:587

# API Facebook Graph
FACEBOOK_PAGE_ID=ententesportivecoutancaise
FACEBOOK_ACCESS_TOKEN=ton_token_long_lived_ici
```

> ⚠️ **Ne jamais commiter `.env.local`** — il est dans le `.gitignore`.

---

## 📱 Pages du site

| URL | Description |
|---|---|
| `/` | Page d'accueil |
| `/club/histoire` | Histoire du club (éditable) |
| `/club/organigramme` | → redirige vers `/club/encadrement` |
| `/club/encadrement` | Encadrement par catégorie |
| `/club/infrastructure` | Infrastructure (éditable) |
| `/contact` | Formulaire de contact |
| `/galerie` | Galerie photos |
| `/actualites` | Toutes les actualités |
| `/actualites/communiques` | Communiqués |
| `/actualites/medias` | Médias |
| `/actualites/{slug}` | Détail d'un article |
| `/admin` | Back-office EasyAdmin |
| `/admin/login` | Page de connexion admin |

---

## 🔧 Commandes utiles

```bash
# Vider le cache (avec suffisamment de mémoire)
php -d memory_limit=512M bin/console cache:clear

# Créer un utilisateur
php bin/console app:create-user email@exemple.fr MotDePasse "Prénom Nom" ROLE_ADMIN

# Générer une migration après modification d'entité
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Créer une entité
php bin/console make:entity NomEntite

# Créer un CrudController EasyAdmin
php bin/console make:admin:crud

# Lister les routes
php bin/console debug:router

# Vérifier la config des services
php bin/console debug:container FacebookService
```

---

## 🖼️ Gestion des images

Les images sont uploadées via **VichUploader** et stockées dans `public/uploads/`.  
Ce dossier est exclu du dépôt Git — penser à le sauvegarder séparément.

| Mapping | Dossier | Utilisé pour |
|---|---|---|
| `slide_image` | `/uploads/slides/` | Carousel hero |
| `offre_image` | `/uploads/offres/` | Offres d'emploi |
| `membre_photo` | `/uploads/membres/` | Encadrement |
| `partenaire_logo` | `/uploads/partenaires/` | Partenaires & sponsors |

---

## 📘 API Facebook Graph (Non fonctionnel)

Le site récupère les dernières publications de la page Facebook du club via l'**API Graph v19**.

- Les posts sont mis en **cache 30 minutes** (Symfony Cache)
- En cas d'erreur API, le site affiche les boutons réseaux sociaux en fallback
- Le token doit être renouvelé tous les **~60 jours** (token long-lived)

Pour renouveler le token :
```
https://developers.facebook.com/tools/explorer
```

---

## 🚀 Déploiement en production

```bash
# Passer en mode production
APP_ENV=prod dans .env.local

# Installer les dépendances sans dev
composer install --no-dev --optimize-autoloader

# Vider et préchauffer le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Migrations
php bin/console doctrine:migrations:migrate --env=prod
```

---

## 👨‍💻 Développeur

**Evan MOREAU** — Cylaos ICT  
📧 evan.moreau@etik.com  
🌐 [cylaos.fr](https://www.cylaos.com/)

---

## 📄 Licence

Projet propriétaire — © 2026 Evan MOREAU.  
Tous droits réservés. Voir le fichier [LICENSE](./LICENSE) pour les détails.
